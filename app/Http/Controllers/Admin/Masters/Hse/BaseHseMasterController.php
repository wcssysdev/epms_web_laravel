<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Http\Controllers\Admin\Masters\BaseGlobalCrudController;
use App\Http\Controllers\Admin\Masters\BaseMasterController;
use App\Services\AuditService;
use App\Services\HsePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Base for GLOBAL (non-tenant) HSE Portal–synced masters.
 *
 * Extends the shared {@see BaseMasterController} to reuse its index / getDatatable /
 * upload / preview / saveUploadedData / cancelUpload / exportMasterData / generateCsv
 * scaffolding, but replaces the SAP two-step sync with an HSE Portal three-step flow
 * (Get All Data From HSE → Refresh Master Data Info → Replace Master Data) backed by
 * the `m_master_*` staging mirror tables.
 *
 * HSE master tables are GLOBAL — the DDL has no `company_id` column — so reads drop
 * company scoping (mirroring {@see BaseGlobalCrudController}),
 * and the SAP pipeline is disabled via {@see sapConfig()} returning null.
 *
 * The three-step flow methods (all implemented) use the existing
 * {@see HsePortalService} (get/post/fetchMasterData, fromConfig()):
 *   - getFromHse(): JsonResponse            → STEP 1: portal → staging (truncate + insert)
 *   - stagingInfo(): JsonResponse           → STEP 2: {current_rows, new_rows} (overrides base SAP stagingInfo)
 *   - replaceFromStaging(): RedirectResponse → STEP 3: staging → master (tx + rollback, concurrency lock,
 *                                              manual identity-key assignment for identity-keyed masters)
 */
abstract class BaseHseMasterController extends BaseMasterController
{
    // ── HSE-specific contract each child must define ─────────────────────────

    /** HSE Portal endpoint path, e.g. 'MasterData/category-of-injuries'. */
    abstract protected function hseEndpoint(): string;

    /** Staging mirror table, e.g. 'm_master_category_of_injuries'. */
    abstract protected function stagingTable(): string;

    /** Map one portal JSON item to a staging row (portal field names → staging columns). */
    abstract protected function mapPortalItem(array $item): array;

    /** Columns copied staging → master during Replace, as [staging_column => master_column]. */
    abstract protected function stagingToMasterMap(): array;

    // ── Overridden base scoping ──────────────────────────────────────────────

    /**
     * HSE masters are global (no company_id). Drop the company-scoped where filter
     * that {@see BaseMasterController::baseQuery()} applies — same approach as
     * {@see BaseGlobalCrudController::baseQuery()}.
     */
    protected function baseQuery()
    {
        return DB::table($this->tableName());
    }

    /**
     * Disable the SAP two-step flow for HSE masters; sync is handled by the HSE Portal
     * three-step flow instead. Returning null keeps $hasSap false in the inherited index.
     */
    protected function sapConfig(): ?array
    {
        return null;
    }

    // ── Shared hooks ─────────────────────────────────────────────────────────

    /**
     * Natural/business key column used for dedupe and ordering per master.
     * Defaults to the primary key column; override when the master is keyed by a
     * business column (e.g. 'ploc_id', 'risk_acceptability_name', 'course_no_epms').
     */
    protected function masterKeyColumn(): string
    {
        return 'id';
    }

    /**
     * Inject audit fields on a master row before insert during Replace.
     *
     * HSE master DDL uses `created_by` / `created_date` / `updated_by` / `updated_date`
     * audit columns (not Laravel's created_at/updated_at). Children with differently
     * named audit columns (e.g. ploc_* / lhood_* / severity_* prefixes) should override.
     */
    protected function addAuditFields(array $row): array
    {
        $now = now();
        $user = $this->userName();

        $row['created_by'] = $row['created_by'] ?? $user;
        $row['created_date'] = $row['created_date'] ?? $now;
        $row['updated_by'] = $user;
        $row['updated_date'] = $now;

        return $row;
    }

    /**
     * Whether this master is keyed by an integer `id` for which the HSE Portal supplies
     * no database-generated identity default. When true, the Replace step assigns each
     * inserted row a sequential id starting at COALESCE(MAX(id),0)+1.
     *
     * Defaults to false (natural/business-key or sequence-backed masters). The five
     * identity-keyed masters (category_of_injuries, type_of_injury, type_of_disease,
     * type_of_accident, first_aid_kit) override this to return true.
     */
    protected function usesManualIdentityKey(): bool
    {
        return false;
    }

    // ── CSV import persist (override: global scope, HSE audit cols, dedupe) ───

    /**
     * Session key under which {@see BaseMasterController::preview()} stores the
     * validated (row-passing) CSV rows. The base keeps this private, so the format
     * is replicated here verbatim to read the same bucket.
     */
    private function hsePreviewSessionKey(): string
    {
        return 'master_preview_'.$this->tableName();
    }

    /**
     * STEP "Save uploaded data" for HSE masters — OVERRIDES
     * {@see BaseMasterController::saveUploadedData()}.
     *
     * The base implementation is SAP/tenant-shaped: it injects `company_id` and the
     * Laravel `created_at`/`updated_at` timestamps, and it inserts every previewed row
     * verbatim. Neither fits the global HSE masters, which have NO `company_id` column
     * and use per-master audit columns (created_by/created_date, or the ploc_* / lhood_*
     * / severity_* prefixed variants supplied by each child's {@see addAuditFields()}).
     * This override therefore:
     *
     *   - R5.5 (non-empty natural key): drops any previewed row whose
     *     {@see masterKeyColumn()} value is empty. `validateRow()` already rejects a
     *     blank key during preview, so this is a defensive second gate on the saved set.
     *   - R5.5 (global uniqueness / dedupe before insert): deduplicates the previewed
     *     rows so each natural-key value is inserted at most once (first occurrence wins,
     *     mirroring CI3's serialize/unique dedupe), AND skips any key that already exists
     *     in the live master — so the natural key stays globally unique within the master.
     *   - Applies {@see addAuditFields()} (the master's real audit columns) instead of
     *     the base's company_id/created_at/updated_at.
     *   - R5.6: persists the surviving rows inside one transaction and reports the count.
     *
     * The previewed rows are read from the SAME session bucket the inherited
     * {@see BaseMasterController::preview()} writes, so the upload → preview → save flow
     * and its Blade views are reused unchanged.
     */
    public function saveUploadedData(): RedirectResponse
    {
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return redirect()->route($this->routePrefix().'.index')
                ->with('error', 'System is locked. Please contact your manager.');
        }

        $rows = Session::get($this->hsePreviewSessionKey(), []);

        if (empty($rows)) {
            return redirect()->route($this->routePrefix().'.index')
                ->with('error', 'No preview data found. Please upload again.');
        }

        $keyCol = $this->masterKeyColumn();

        // Keys already present in the live master — a natural key must stay globally
        // unique within the master, so CSV rows duplicating an existing key are skipped.
        $existingKeys = DB::table($this->tableName())
            ->pluck($keyCol)
            ->map(fn ($v) => (string) $v)
            ->flip();          // value => index, for O(1) membership tests

        $seen = [];          // natural keys accepted so far in THIS upload
        $toInsert = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $key = isset($row[$keyCol]) ? trim((string) $row[$keyCol]) : '';

            // R5.5: a non-empty natural key is required.
            if ($key === '') {
                $skipped++;

                continue;
            }

            // R5.5: dedupe within the batch and against the existing master.
            if (isset($seen[$key]) || $existingKeys->has($key)) {
                $skipped++;

                continue;
            }

            $seen[$key] = true;
            $toInsert[] = $this->addAuditFields($row);
        }

        if (empty($toInsert)) {
            return redirect()->route($this->routePrefix().'.index')
                ->with('error', 'No new '.strtolower($this->resourceName())
                    .' rows to import (all rows were empty-keyed or duplicates).');
        }

        DB::transaction(function () use ($toInsert) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                DB::table($this->tableName())->insert($chunk);
            }
            $this->touchLog(['last_updated_at' => now(), 'last_updated_by' => $this->userId()]);
        });

        Session::forget($this->hsePreviewSessionKey());

        $inserted = count($toInsert);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_CREATE,
            'Uploaded '.$inserted.' rows to '.$this->resourceName().' via CSV'
            .($skipped > 0 ? " ({$skipped} skipped — empty key or duplicate)" : '')
        );

        $msg = $inserted.' '.$this->resourceName().' records saved successfully.'
            .($skipped > 0 ? " {$skipped} row(s) skipped (empty key or duplicate natural key)." : '');

        return redirect()->route($this->routePrefix().'.index')->with('success', $msg);
    }

    // ── Three-step HSE Portal flow ───────────────────────────────────────────

    /**
     * STEP 3 — "Replace Master Data": swap the live master for the staged portal rows,
     * atomically, with rollback.
     *
     * Flow (per the design `replaceFromStaging()` spec + "Replace Master Data (STEP 3) —
     * with rollback" pseudocode):
     *   - Reject the operation when the system is locked for a non-privileged role
     *     (mirrors the SAP {@see BaseMasterController::refreshFromMaster()} lock guard,
     *     surfaced here as a flash-redirect because this is a form-post action).
     *   - PRECONDITION (R4.4): if {@see stagingTable()} is empty, make NO change and
     *     redirect back with an error prompting the operator to run Get first.
     *   - CONCURRENCY (R4.7): acquire an atomic {@see Cache::lock()} keyed by the master
     *     table name so a second concurrent Replace for the SAME master is rejected with
     *     an "already running" error. The lock is always released in the finally block.
     *   - Snapshot the current master rows into memory for the rollback fallback. The DB
     *     transaction itself provides atomicity (R4.1/R4.5); the snapshot mirrors CI3's
     *     `replace_master_data()` and documents the exact prior contents that survive a
     *     rollback.
     *   - Inside one {@see DB::transaction()}: DELETE every master row; for identity-keyed
     *     masters compute `$nextId = COALESCE(MAX(id),0)+1` AFTER the delete (R4.8/R4.9);
     *     translate each staging row via {@see stagingToMasterMap()}, apply
     *     {@see addAuditFields()}, assign the manual id when required, insert, and count.
     *     A count mismatch (`$inserted !== staging count`) throws to force a rollback (R4.5).
     *   - On success: commit, write an {@see AuditService} replace entry carrying the
     *     inserted count (R13.5), and redirect back with a success flash (R4.2). Staging is
     *     left intact so the operation is idempotently re-runnable (R4.6).
     *   - On any exception: the transaction rolls back and the master retains its exact
     *     prior contents (R4.5); redirect back with a safe rollback error.
     *
     * Loop invariants (enforced in the loop below):
     *   (1) `$inserted` equals the number of rows written so far;
     *   (2) each written row carries all mapped master columns + audit fields (+ `id` when
     *       the master uses a manual identity key);
     *   (3) for identity-keyed masters, `$nextId` is strictly greater than every id already
     *       assigned in this run.
     */
    public function replaceFromStaging(): RedirectResponse
    {
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return redirect()->back()->with('error', 'System is locked. Please contact your manager.');
        }

        $resource = $this->resourceName();

        // PRECONDITION (R4.4): no staged data → no change, prompt Get first.
        $stagingRows = DB::table($this->stagingTable())->get();
        if ($stagingRows->isEmpty()) {
            return redirect()->back()
                ->with('error', "No staged data. Click 'Get All Data From HSE' first.");
        }

        // CONCURRENCY (R4.7): one Replace at a time per master table. A short lease keeps a
        // crashed request from wedging the lock indefinitely; we release explicitly below.
        $lock = Cache::lock('hse_replace:'.$this->tableName(), 120);

        if (! $lock->get()) {
            return redirect()->back()
                ->with('error', 'A replace for '.strtolower($resource).' is already running. Please wait and try again.');
        }

        try {
            // In-memory snapshot of the pre-operation master, for rollback fidelity (the DB
            // transaction already guarantees atomicity; this mirrors CI3's snapshot fallback).
            $oldMaster = $this->baseQuery()->get();
            $stagingCount = $stagingRows->count();

            $inserted = 0;
            DB::transaction(function () use ($stagingRows, $stagingCount, &$inserted) {
                // Replace the entire master (HSE masters are global — no company filter).
                DB::table($this->tableName())->delete();

                // Identity-keyed masters: the portal supplies no DB-generated id default, so
                // EPMS assigns ids manually. Computed AFTER the delete — COALESCE(MAX(id),0)+1,
                // i.e. MAX of an empty table is null → starts at 1 — preserving the CI3 formula.
                $manualId = $this->usesManualIdentityKey();
                $nextId = $manualId
                    ? ((int) DB::table($this->tableName())->max('id')) + 1
                    : 0;

                $map = $this->stagingToMasterMap();

                foreach ($stagingRows as $stg) {
                    $stgArr = (array) $stg;

                    $row = [];
                    foreach ($map as $stagingCol => $masterCol) {
                        $row[$masterCol] = $stgArr[$stagingCol] ?? null;
                    }

                    $row = $this->addAuditFields($row);

                    if ($manualId) {
                        // Invariant (3): $nextId strictly greater than every id assigned so far.
                        $row['id'] = $nextId;
                        $nextId++;
                    }

                    DB::table($this->tableName())->insert($row);

                    // Invariant (1): $inserted tracks rows written so far.
                    $inserted++;
                }

                // Count fidelity (R4.3/R4.5): every staged row must have landed, else roll back.
                if ($inserted !== $stagingCount) {
                    throw new \RuntimeException(
                        "Replace count mismatch: inserted {$inserted} of {$stagingCount} staged rows."
                    );
                }

                $this->touchLog([
                    'last_updated_at' => now(),
                    'last_updated_by' => $this->userId(),
                    'is_replaced' => true,
                ]);
            });

            // Snapshot is retained only as a documented fallback; a committed transaction needs
            // no restore. Reference it so static analysers see the rollback contract is modelled.
            unset($oldMaster);

            AuditService::log(
                AuditService::TYPE_MASTER,
                AuditService::ACTION_UPDATE,
                'Replaced '.$resource.' master from HSE staging ('.$inserted.' rows)'
            );

            return redirect()->back()
                ->with('success', $inserted.' '.$resource.' records refreshed from HSE staging.');
        } catch (\Throwable $e) {
            // The transaction has rolled back; the master retains its exact prior contents (R4.5).
            return redirect()->back()
                ->with('error', 'Replace failed, transaction rolled back: '.$e->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * STEP 2 — "Refresh Master Data Info": compare the live master against staging.
     *
     * OVERRIDES {@see BaseMasterController::stagingInfo()}, whose SAP-flavoured
     * {@see BaseMasterController::stagingCounts()} derives `new_rows` from the
     * company-scoped SAP staging table. For HSE masters the staging mirror is the
     * portal-fed {@see stagingTable()} and reads are global (no `company_id`), so
     * the counts are taken directly:
     *   - `current_rows` = COUNT of the live master via {@see baseQuery()}
     *     (already stripped of company scoping in this class);
     *   - `new_rows`     = COUNT of the HSE staging mirror.
     *
     * The response shape is identical to the base — `jsonSuccess('OK', [...])`
     * carrying the same `current_rows` / `new_rows` keys the inherited Blade /
     * DataTables info bar consumes — so no view/JS changes are required.
     *
     * Strictly read-only: neither the master nor the staging table is mutated,
     * and no audit entry is written (per the design `stagingInfo()` spec:
     * "returns `{current_rows, new_rows}`; read-only, no side effects").
     */
    public function stagingInfo(): JsonResponse
    {
        return $this->jsonSuccess('OK', [
            'current_rows' => $this->baseQuery()->count(),
            'new_rows' => DB::table($this->stagingTable())->count(),
        ]);
    }

    /**
     * STEP 1 — "Get All Data From HSE": pull HSE Portal rows into the staging table.
     *
     * Flow (per the design `getFromHse()` spec + "Get All Data From HSE" pseudocode):
     *   - Probe the endpoint with {@see HsePortalService::get()} to inspect the exact
     *     HTTP status / payload so the four distinct error causes can be reported:
     *       R2.5 non-200 HTTP status, R2.6 empty payload, R2.7 unreachable/timeout,
     *       R2.8 unparseable payload (plus the config-missing case the service reports
     *       as status_code 0 with a configuration message).
     *   - On HTTP 200 with a non-empty, recognizable payload, collect the FULL paginated
     *     result via {@see HsePortalService::fetchMasterData()}, then inside one DB
     *     transaction truncate {@see stagingTable()} and batch-insert the
     *     {@see mapPortalItem()}-mapped rows.
     *   - Write an {@see AuditService} fetch entry and return JSON success with the staged
     *     count.
     *
     * The live master table ({@see tableName()}) is NEVER touched by this step; only the
     * staging mirror is mutated, and only on the success path.
     */
    public function getFromHse(): JsonResponse
    {
        if ($this->hseEndpoint() === '') {
            return $this->jsonError('HSE Portal endpoint is not configured for this master.');
        }
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $service = HsePortalService::fromConfig();

        // Probe once to inspect the raw status/payload and distinguish the error causes.
        $probe = $service->get($this->hseEndpoint());
        $status = (int) ($probe['status_code'] ?? 0);
        $data = $probe['data'] ?? null;

        // Config-missing / unreachable / timeout → the service reports status_code 0.
        //   - hse_api_url unset/empty/whitespace → a fixed configuration message (R12.6).
        //   - connection refused / DNS / TLS / 30s timeout → the exception message (R2.7).
        if ($status === 0) {
            $message = is_string($data) ? $data : '';

            if (stripos($message, 'not configured') !== false) {
                return $this->jsonError('HSE Portal API URL is not configured. Set it in Company Config first.');
            }

            return $this->jsonError(
                'Could not reach the HSE Portal (connection failed or timed out)'
                .($message !== '' ? ': '.$message : '.')
            );
        }

        // Non-200 HTTP status → identify the status (R2.5).
        if ($status !== 200) {
            return $this->jsonError('Error requesting master data from HSE Portal (HTTP '.$status.').');
        }

        // HTTP 200 but the body is not a recognizable master-data payload → unparseable (R2.8).
        // The service returns the raw (string) body when JSON decoding fails; a decoded but
        // unrecognized shape (scalar / non-list map without `items`) is equally unusable.
        $probeItems = $this->normalizePortalPayload($data);
        if ($probeItems === null) {
            return $this->jsonError('HSE Portal returned an unreadable response for '.strtolower($this->resourceName()).'.');
        }

        // HTTP 200 with a recognizable but empty payload → empty result (R2.6).
        if ($probeItems === []) {
            return $this->jsonError('HSE Portal returned no '.strtolower($this->resourceName()).' data.');
        }

        // Happy path: collect the FULL paginated result set (the probe above is page-less;
        // fetchMasterData walks ?pageNumber/?pageSize and concatenates in order).
        $items = $service->fetchMasterData($this->hseEndpoint());

        // Defensive: if pagination collapsed to nothing (e.g. a transient non-200 on a later
        // page after the probe succeeded), treat it as an empty result rather than wiping
        // staging with no data.
        if ($items === []) {
            return $this->jsonError('HSE Portal returned no '.strtolower($this->resourceName()).' data.');
        }

        // Map every portal item to a staging row, restricting keys to the staging columns
        // (mapPortalItem is the subclass contract for that projection).
        $stagingRows = [];
        foreach ($items as $item) {
            $stagingRows[] = $this->mapPortalItem((array) $item);
        }

        // Truncate + batch-insert staging in one transaction. No Eloquent model backs the
        // staging mirror (matching the SAP staging convention), so use the query builder.
        DB::transaction(function () use ($stagingRows) {
            DB::table($this->stagingTable())->truncate();

            foreach (array_chunk($stagingRows, 500) as $chunk) {
                DB::table($this->stagingTable())->insert($chunk);
            }
        });

        $count = count($stagingRows);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_UPDATE,
            'Fetched '.$count.' '.$this->resourceName().' rows from HSE Portal into staging'
        );

        return $this->jsonSuccess(
            $count.' rows fetched into staging.',
            $this->stagingCounts()
        );
    }

    /**
     * Normalize a raw 200 payload (from {@see HsePortalService::get()}) into a flat item
     * list, mirroring the service's own normalization so getFromHse can distinguish an
     * empty payload (`[]`) from an unparseable one (`null`).
     *
     *   - top-level JSON array        → used directly (verified live-portal shape);
     *   - `{ "items": [...] }`        → the `items` list (defensive fallback);
     *   - anything else (raw string   → null (unrecognized / unparseable).
     *     body, scalar, map w/o items)
     *
     * @return array<int, mixed>|null
     */
    private function normalizePortalPayload(mixed $data): ?array
    {
        if (is_array($data) && array_is_list($data)) {
            return $data;
        }

        if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
            return array_values($data['items']);
        }

        return null;
    }
}
