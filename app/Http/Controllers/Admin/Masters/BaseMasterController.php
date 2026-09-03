<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

abstract class BaseMasterController extends BaseController
{
    // ── Child must define these ───────────────────────────────────────────────

    /** Eloquent model class string e.g. \App\Models\Master\Estate::class */
    abstract protected function modelClass(): string;

    /** DB table name e.g. 'm_estate' */
    abstract protected function tableName(): string;

    /** Human-readable name e.g. 'Estate' */
    abstract protected function resourceName(): string;

    /** Columns to show in DataTables [db_column => label] */
    abstract protected function datatableColumns(): array;

    /** CSV headers for template download */
    abstract protected function csvHeaders(): array;

    /** Map CSV row to DB insert array */
    abstract protected function mapCsvRow(array $row, int $rowNum): array|null;

    /** Validate a mapped row, return error string or null */
    abstract protected function validateRow(array $row): ?string;

    /**
     * SAP integration config for this master. Return null to disable SAP sync.
     *
     * Expected shape:
     *   [
     *     'staging'  => 'ZEPMS_EM_ESTATE_OUT',       // staging table
     *     'urn'      => 'ZEPMS_EM_ESTATE_OUT',       // SAP URN (usually same)
     *     'filters'  => ['BUKRS' => '*', 'LAND1' => '{country_code}'],  // {tokens} resolved from context
     *     'columns'  => ['BUKRS','ESTNR','NAME1','WERKS'],  // SAP field columns stored in staging
     *     'mapping'  => ['company_id' => null, 'estate_code' => 'ESTNR', ...], // master col => SAP field (null = special)
     *   ]
     */
    protected function sapConfig(): ?array
    {
        return null;
    }

    /** Transform a mapped master row before insert (dates, booleans). Override per master. */
    protected function transformSapRow(array $master, array $staging): array
    {
        return $master;
    }

    // ── Optional overrides ────────────────────────────────────────────────────

    /** Extra filters applied on index/datatable query */
    protected function baseQuery()
    {
        return DB::table($this->tableName())
            ->where('company_id', $this->companyId());
    }

    /** View prefix e.g. 'admin.masters.estate' */
    protected function viewPrefix(): string
    {
        return 'admin.masters.' . strtolower(str_replace(' ', '_', $this->resourceName()));
    }

    /** Route prefix e.g. 'masters.estate' */
    protected function routePrefix(): string
    {
        return 'masters.' . strtolower(str_replace(' ', '_', $this->resourceName()));
    }

    // ── Session key for preview data ──────────────────────────────────────────
    private function previewSessionKey(): string
    {
        return 'master_preview_' . $this->tableName();
    }

    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $log = DB::table('master_data_log')
            ->where('company_id', $this->companyId())
            ->where('table_name', $this->tableName())
            ->first();

        $counts = $this->stagingCounts();

        return view($this->viewPrefix() . '.index', [
            'resourceName' => $this->resourceName(),
            'totalRows'    => $counts['current_rows'],
            'newRows'      => $counts['new_rows'],
            'hasSap'       => $this->sapConfig() !== null,
            'lastUpdate'   => $log?->last_updated_at,
            'lastRefresh'  => $log?->last_refresh_at,
            'routePrefix'  => $this->routePrefix(),
            'columns'      => $this->datatableColumns(),
        ]);
    }

    // ── DATATABLE AJAX ────────────────────────────────────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderByDesc('id');

        return DataTables::queryBuilder($query)
            ->addIndexColumn()
            ->make(true);
    }

    // ── UPLOAD form ───────────────────────────────────────────────────────────
    public function upload()
    {
        return view($this->viewPrefix() . '.upload', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
        ]);
    }

    // ── PREVIEW uploaded CSV ──────────────────────────────────────────────────
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));

        if (empty($rows)) {
            return back()->with('error', 'CSV file is empty.');
        }

        $headers  = array_map('trim', array_shift($rows));
        $preview  = [];
        $errors   = [];
        $valid    = [];

        foreach ($rows as $i => $row) {
            if (count($row) === 1 && empty(trim($row[0]))) continue;

            $mapped = $this->mapCsvRow(array_combine($headers, array_pad($row, count($headers), '')), $i + 2);
            if ($mapped === null) continue;

            $error = $this->validateRow($mapped);
            if ($error) {
                $errors[] = "Row " . ($i + 2) . ": {$error}";
            } else {
                $valid[] = $mapped;
            }

            // Show max 100 rows in preview
            if (count($preview) < 100) {
                $preview[] = ['data' => $mapped, 'error' => $error];
            }
        }

        // Store valid rows in session
        session([$this->previewSessionKey() => $valid]);

        return view($this->viewPrefix() . '.preview', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'preview'      => $preview,
            'validCount'   => count($valid),
            'errorCount'   => count($errors),
            'errors'       => array_slice($errors, 0, 20),
            'headers'      => array_keys($this->datatableColumns()),
        ]);
    }

    // ── SAVE uploaded data ────────────────────────────────────────────────────
    public function saveUploadedData(): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $rows = session($this->previewSessionKey(), []);

        if (empty($rows)) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', 'No preview data found. Please upload again.');
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // Add company scope + audit fields
                $row['company_id']  = $this->companyId();
                $row['created_by']  = $row['created_by'] ?? $this->userName();
                $row['updated_by']  = $this->userName();
                $row['updated_at']  = now();
                $row['created_at']  = $row['created_at'] ?? now();

                DB::table($this->tableName())->insert($row);
            }
            $this->logMasterDataUpdate(count($rows));
        });

        session()->forget($this->previewSessionKey());

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_CREATE,
            "Uploaded " . count($rows) . " rows to " . $this->resourceName()
        );

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', count($rows) . ' ' . $this->resourceName() . ' records saved successfully.');
    }

    // ── CANCEL preview ────────────────────────────────────────────────────────
    public function cancelUpload(): RedirectResponse
    {
        session()->forget($this->previewSessionKey());
        return redirect()->route($this->routePrefix() . '.index');
    }

    // ════════════════════════════════════════════════════════════════════════
    // SAP TWO-STEP FLOW
    // ════════════════════════════════════════════════════════════════════════

    /** STEP 1 — "Get All Data From SAP": pull SAP rows into the staging table. */
    public function getFromSap(): JsonResponse
    {
        $sap = $this->sapConfig();
        if (! $sap) {
            return $this->jsonError('SAP sync is not configured for this master.');
        }
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $service = app(\App\Services\SapService::class);
        $ctx     = $service->context($this->companyId());

        // Resolve {tokens} in filters from context
        $filters = [];
        foreach ($sap['filters'] as $k => $v) {
            $filters[$k] = is_string($v) ? strtr($v, [
                '{country_code}' => $ctx['country_code'],
                '{company_code}' => $ctx['company_code'],
            ]) : $v;
        }

        $result = $service->fetchMasterData($sap['urn'], $filters, $ctx, $sap['columns']);

        if ($result['status_code'] !== 200) {
            return $this->jsonError('Error requesting master data from SAP (HTTP ' . $result['status_code'] . ').');
        }
        if (empty($result['data'])) {
            return $this->jsonError('SAP returned no ' . strtolower($this->resourceName()) . ' data.');
        }

        // Truncate + insert staging, scoped by company_code (only this company's rows)
        DB::transaction(function () use ($sap, $result, $ctx) {
            DB::table($sap['staging'])->where('company_code', $ctx['company_code'])->delete();

            foreach ($result['data'] as $item) {
                $row = [
                    'company_code' => $ctx['company_code'],
                    'country_code' => $ctx['country_code'],
                    'country_no'   => $ctx['country_no'],
                    'fetched_at'   => now(),
                ];
                foreach ($sap['columns'] as $col) {
                    $row[$col] = $item[$col] ?? null;
                }
                DB::table($sap['staging'])->insert($row);
            }

            $this->touchLog(['last_refresh_at' => now()]);
        });

        AuditService::log(
            AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE,
            "Fetched " . count($result['data']) . " " . $this->resourceName() . " rows from SAP into staging"
            . ($result['simulated'] ? ' (simulated)' : '')
        );

        return $this->jsonSuccess(
            count($result['data']) . ' rows fetched into staging'
            . ($result['simulated'] ? ' (SAP simulated — no live endpoint configured)' : '') . '.',
            $this->stagingCounts()
        );
    }

    /** STEP 2 — "Refresh Master Data From SAP": staging → master with backup/rollback. */
    public function refreshFromMaster(): JsonResponse
    {
        $sap = $this->sapConfig();
        if (! $sap) {
            return $this->jsonError('SAP sync is not configured for this master.');
        }
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $ctx = app(\App\Services\SapService::class)->context($this->companyId());

        $stagingRows = DB::table($sap['staging'])
            ->where('company_code', $ctx['company_code'])
            ->get();

        if ($stagingRows->isEmpty()) {
            return $this->jsonError('No staged data. Run "Get All Data From SAP" first.');
        }

        try {
            $inserted = 0;
            DB::transaction(function () use ($sap, $stagingRows, &$inserted) {
                // Replace this company's master rows
                DB::table($this->tableName())->where('company_id', $this->companyId())->delete();

                foreach ($stagingRows as $stg) {
                    $stgArr = (array) $stg;
                    $master = [
                        'company_id' => $this->companyId(),
                        'created_by' => $this->userName(),
                        'updated_by' => $this->userName(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    foreach ($sap['mapping'] as $masterCol => $sapField) {
                        if ($sapField === null) continue; // special / already set
                        $master[$masterCol] = $stgArr[$sapField] ?? null;
                    }
                    $master = $this->transformSapRow($master, $stgArr);
                    DB::table($this->tableName())->insert($master);
                    $inserted++;
                }

                $this->touchLog(['last_updated_at' => now(), 'last_updated_by' => $this->userId(), 'is_replaced' => true]);
            });

            AuditService::log(
                AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE,
                "Refreshed " . $this->resourceName() . " master from staging ({$inserted} rows)"
            );

            return $this->jsonSuccess(
                "{$inserted} " . $this->resourceName() . ' records refreshed from SAP staging.',
                $this->stagingCounts()
            );
        } catch (\Throwable $e) {
            return $this->jsonError('Refresh failed, transaction cancelled: ' . $e->getMessage());
        }
    }

    /** Current (master) vs new (staging) row counts for the info bar. */
    public function stagingInfo(): JsonResponse
    {
        return $this->jsonSuccess('OK', $this->stagingCounts());
    }

    protected function stagingCounts(): array
    {
        $sap = $this->sapConfig();
        $ctx = app(\App\Services\SapService::class)->context($this->companyId());

        $newRows = 0;
        if ($sap && Schema::hasTable($sap['staging'])) {
            $newRows = DB::table($sap['staging'])->where('company_code', $ctx['company_code'])->count();
        }

        return [
            'current_rows' => $this->baseQuery()->count(),
            'new_rows'     => $newRows,
        ];
    }

    // ── EXPORT actual master data (with values) ───────────────────────────────
    public function exportMasterData(): StreamedResponse
    {
        $headers = $this->csvHeaders();
        $rows    = $this->baseQuery()->get();
        $cols    = array_keys($this->datatableColumns());
        $filename = strtolower(str_replace(' ', '_', $this->resourceName())) . '_' . now()->format('Y-m-d') . '.csv';

        $callback = function () use ($headers, $rows, $cols) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                $arr  = (array) $row;
                $line = array_map(fn ($c) => $arr[$c] ?? '', $cols);
                fputcsv($handle, $line);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── GENERATE CSV template (empty, for upload) ─────────────────────────────
    public function generateCsv(): StreamedResponse
    {
        $headers = $this->csvHeaders();
        $filename = strtolower(str_replace(' ', '_', $this->resourceName())) . '_template.csv';

        $callback = function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, array_fill(0, count($headers), ''));
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── master_data_log helper ────────────────────────────────────────────────
    protected function touchLog(array $fields): void
    {
        DB::table('master_data_log')->updateOrInsert(
            ['company_id' => $this->companyId(), 'table_name' => $this->tableName()],
            $fields
        );
    }
}
