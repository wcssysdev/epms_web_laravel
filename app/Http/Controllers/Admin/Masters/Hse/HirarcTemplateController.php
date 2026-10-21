<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use App\Services\HsePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * HIRARC Template — fetch-to-staging ONLY (in scope for the Master HSE admin migration).
 *
 * Unlike the 10 portal-synced masters, HIRARC Template has NO live master table of its own.
 * It fetches HIRARC templates from the HSE Portal into the `zhse_hirarc_template` staging
 * table and shows a compare view (staged rows vs the "current" transaction table `t_hirarc`).
 * It therefore does NOT subclass {@see BaseHseMasterController} (no m_* master, no
 * stagingToMasterMap, no DataTable CSV scaffolding); it extends the plain
 * {@see BaseController} the other simple admin controllers (e.g. ConfigController,
 * AuditTrailController) use, reusing its userName()/jsonSuccess()/jsonError() helpers.
 *
 * Portal contract (verified base roots): HIRARC lives under a dedicated path root
 * `/api/hirarc` (`/all`, `/published`, `/requested`, `/{code}`) — NOT a `MasterData/*`
 * path. {@see HsePortalService} already supports non-MasterData endpoint roots.
 * This controller targets {@see self::HSE_ENDPOINT} ('hirarc/all').
 *
 * ──────────────────────────────────────────────────────────────────────────────────────
 * OUT OF SCOPE (Requirements 11.3 / 13.6): applyTemplate().
 *   Writing staged templates into `t_hirarc` (+ children) is a HIRARC *transaction* and is
 *   DELIBERATELY NOT IMPLEMENTED here. It is deferred to a future "HIRARC transaction" spec.
 *   See {@see self::applyTemplate()} below — it is a disabled stub that only explains the
 *   deferral; do NOT add any t_hirarc write here.
 * ──────────────────────────────────────────────────────────────────────────────────────
 */
class HirarcTemplateController extends BaseController
{
    /**
     * HSE Portal endpoint for the HIRARC template fetch (non-MasterData root).
     * The portal also exposes 'hirarc/published'; 'hirarc/all' is used so staff can review
     * every template, not just published ones.
     */
    private const HSE_ENDPOINT = 'hirarc/all';

    /** Staging table this controller fetches into (truncate + insert on every Get). */
    private const STAGING_TABLE = 'zhse_hirarc_template';

    /**
     * The "current" side of the compare is the HIRARC transaction table. It is NOT created
     * by this feature (it is a transaction table, out of scope) and may be absent from the
     * Laravel DB. Every read of it is guarded by {@see Schema::hasTable()}.
     */
    private const CURRENT_TABLE = 't_hirarc';

    /**
     * Compare view: staged (zhse_hirarc_template) vs current (t_hirarc) counts + a listing
     * of the staged rows. (R11.2)
     */
    public function index(): View
    {
        $newRows = DB::table(self::STAGING_TABLE)->count();
        $currentRows = $this->currentRowCount();

        // Staged rows for the listing. jsonb snapshots are decoded for display counts only.
        $stagingRows = DB::table(self::STAGING_TABLE)
            ->orderBy('hirarc_no')
            ->get();

        return view('admin.masters.hse.hirarc_template.index', [
            'currentRows' => $currentRows,   // int|null — null when t_hirarc is absent
            'newRows' => $newRows,
            'stagingRows' => $stagingRows,
            'currentExists' => $currentRows !== null,
        ]);
    }

    /**
     * STEP 1 — "Get Template": fetch HIRARC templates from the portal into staging. (R11.1)
     *
     * On HTTP 200 with a non-empty, recognizable payload: truncate + insert the mapped rows
     * inside one transaction. On non-200 / empty / unparseable / unreachable: make NO change
     * to staging and redirect back with a descriptive error.
     *
     * The live transaction table (t_hirarc) is NEVER touched by this step.
     */
    public function getTemplate(): RedirectResponse
    {
        $service = HsePortalService::fromConfig();

        // Probe once to inspect the raw HTTP status/payload so each error cause is reported.
        $probe = $service->get(self::HSE_ENDPOINT);
        $status = (int) ($probe['status_code'] ?? 0);
        $data = $probe['data'] ?? null;

        // status_code 0 → config-missing OR unreachable/timeout (the service reports both this way).
        if ($status === 0) {
            $message = is_string($data) ? $data : '';

            if (stripos($message, 'not configured') !== false) {
                return redirect()->back()
                    ->with('error', 'HSE Portal API URL is not configured. Set it in Company Config first.');
            }

            return redirect()->back()
                ->with('error', 'Could not reach the HSE Portal (connection failed or timed out)'
                    .($message !== '' ? ': '.$message : '.'));
        }

        // Non-200 HTTP status → identify the status.
        if ($status !== 200) {
            return redirect()->back()
                ->with('error', 'Error requesting HIRARC templates from HSE Portal (HTTP '.$status.').');
        }

        // HTTP 200 but body is not a recognizable list payload → unparseable.
        $probeItems = $this->normalizePortalPayload($data);
        if ($probeItems === null) {
            return redirect()->back()
                ->with('error', 'HSE Portal returned an unreadable response for HIRARC templates.');
        }

        // HTTP 200 with a recognizable but empty payload.
        if ($probeItems === []) {
            return redirect()->back()
                ->with('error', 'HSE Portal returned no HIRARC templates.');
        }

        // Collect the FULL paginated result set, then map each item to a staging row.
        $items = $service->fetchMasterData(self::HSE_ENDPOINT);

        if ($items === []) {
            return redirect()->back()
                ->with('error', 'HSE Portal returned no HIRARC templates.');
        }

        $stagingRows = [];
        foreach ($items as $item) {
            $stagingRows[] = $this->mapPortalItem((array) $item);
        }

        // Truncate + batch-insert staging in one transaction (no model backs the staging table).
        DB::transaction(function () use ($stagingRows) {
            DB::table(self::STAGING_TABLE)->truncate();

            foreach (array_chunk($stagingRows, 500) as $chunk) {
                DB::table(self::STAGING_TABLE)->insert($chunk);
            }
        });

        $count = count($stagingRows);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_UPDATE,
            'Fetched '.$count.' HIRARC template(s) from HSE Portal into staging'
        );

        return redirect()->back()
            ->with('success', $count.' HIRARC template(s) fetched into staging.');
    }

    /**
     * STEP 2 — "Refresh Info": compare current (t_hirarc) vs staged (zhse_hirarc_template).
     * Read-only, no side effects. (R11.2)
     *
     * `current_rows` is the t_hirarc count, or 0 when that transaction table does not exist
     * in the Laravel DB yet (t_hirarc is out of scope and may be absent — see
     * {@see self::currentRowCount()}).
     */
    public function refreshInfo(): JsonResponse
    {
        return $this->jsonSuccess('OK', [
            'current_rows' => $this->currentRowCount() ?? 0,
            'new_rows' => DB::table(self::STAGING_TABLE)->count(),
        ]);
    }

    /**
     * OUT OF SCOPE (R11.3 / R13.6) — DO NOT IMPLEMENT a t_hirarc write here.
     *
     * Applying staged templates into the `t_hirarc` transaction table (and its children) is a
     * HIRARC *transaction* behaviour, explicitly excluded from the Master HSE admin migration.
     * It is deferred to a future dedicated "HIRARC transaction" spec. This stub exists only to
     * document that deferral; it is intentionally NOT routed (task 9.1 registers only index /
     * get-template / refresh-info) and must never write to t_hirarc.
     *
     * @codeCoverageIgnore
     */
    public function applyTemplate(): RedirectResponse
    {
        return redirect()->back()->with(
            'error',
            'Apply-to-transaction is out of scope for Master HSE and is deferred to the '
            .'future HIRARC transaction spec. Fetch-to-staging only is available here.'
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Current HIRARC transaction row count, GUARDED for the out-of-scope `t_hirarc` table.
     *
     * Returns the row count when t_hirarc exists, or null when it is absent from the Laravel
     * DB (so callers can show "n/a" for the current side, or coalesce to 0, without erroring).
     */
    private function currentRowCount(): ?int
    {
        if (! Schema::hasTable(self::CURRENT_TABLE)) {
            return null;
        }

        return DB::table(self::CURRENT_TABLE)->count();
    }

    /**
     * Map one portal HIRARC item → a `zhse_hirarc_template` staging row.
     *
     * Columns (from migration 2026_09_17_000001): hirarc_no, hirarc_rev_no, hirarc_rev_date,
     * hirarc_ops_center_code, hirarc_ploc_id, hirarc_approved_by, hirarc_approved_by_designation,
     * hirarc_remark, portal_status, representatives_snapshot (jsonb), details_snapshot (jsonb).
     *
     * DEFENSIVE MAPPING: the exact portal HIRARC field names are not yet verified against the
     * live endpoint, so each column is null-coalesced across the most likely camelCase keys.
     * The representatives/details collections are preserved verbatim as JSON snapshots.
     *
     * TODO(task 11): confirm these portal field names against the live GET /api/hirarc/all
     * response and tighten the mapping (remove the speculative alternates).
     */
    private function mapPortalItem(array $item): array
    {
        return [
            'hirarc_no' => $this->pick($item, ['hirarcNo', 'hirarc_no', 'code', 'no']),
            'hirarc_rev_no' => $this->pick($item, ['revNo', 'hirarcRevNo', 'rev_no', 'revisionNo']),
            'hirarc_rev_date' => $this->pick($item, ['revDate', 'hirarcRevDate', 'rev_date', 'revisionDate']),
            'hirarc_ops_center_code' => $this->pick($item, ['opsCenterCode', 'operationCenterCode', 'opsCenter', 'ops_center_code']),
            'hirarc_ploc_id' => $this->pick($item, ['plocId', 'processLocationCode', 'plocCode', 'ploc_id']),
            'hirarc_approved_by' => $this->pick($item, ['approvedBy', 'hirarcApprovedBy', 'approved_by']),
            'hirarc_approved_by_designation' => $this->pick($item, ['approvedByDesignation', 'approverDesignation', 'approved_by_designation']),
            'hirarc_remark' => $this->pick($item, ['remark', 'hirarcRemark', 'remarks']),
            'portal_status' => $this->pick($item, ['status', 'portalStatus', 'hirarcStatus']),
            // jsonb snapshots — preserve the nested collections verbatim.
            'representatives_snapshot' => $this->jsonSnapshot($item, ['representatives', 'hirarcRepresentatives', 'reps']),
            'details_snapshot' => $this->jsonSnapshot($item, ['details', 'hirarcDetails', 'items', 'rows']),
        ];
    }

    /**
     * First non-null value among the candidate keys of $item, else null.
     */
    private function pick(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                return $item[$key];
            }
        }

        return null;
    }

    /**
     * JSON-encode the first present candidate collection for a jsonb snapshot column, or null
     * when none of the candidate keys is present (leaves the column null rather than "null").
     */
    private function jsonSnapshot(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                return json_encode($item[$key]);
            }
        }

        return null;
    }

    /**
     * Normalize a raw 200 payload into a flat item list (mirrors HsePortalService), so
     * getTemplate() can tell an empty payload ([]) from an unparseable one (null).
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
