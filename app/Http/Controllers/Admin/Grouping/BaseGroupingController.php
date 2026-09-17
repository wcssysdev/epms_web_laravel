<?php

namespace App\Http\Controllers\Admin\Grouping;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseGroupingController extends BaseController
{
    abstract protected function tableName(): string;
    abstract protected function resourceName(): string;
    abstract protected function datatableColumns(): array;
    abstract protected function viewPrefix(): string;
    abstract protected function routePrefix(): string;
    abstract protected function storeData(Request $request): array;
    abstract protected function storeValidation(): array;

    protected function baseQuery()
    {
        return DB::table($this->tableName())->where('company_id', $this->companyId());
    }

    /** Whether this master exposes CSV upload/template/export buttons. */
    protected function hasCsv(): bool
    {
        return true;
    }

    /** Whether this grouping exposes SAP sync. */
    protected function hasSap(): bool
    {
        return $this->sapConfig() !== null;
    }

    /** Whether this grouping exposes "Get All Data From SAP" button (default: false, only refresh). */
    protected function hasGetFromSap(): bool
    {
        return false;
    }

    /** Whether this master exposes a per-row "Print QR" action. */
    protected function hasQr(): bool
    {
        return false;
    }

    /** SAP configuration override in child controller. */
    protected function sapConfig(): ?array
    {
        return null;
    }

    protected function transformSapRow(array $master, array $staging): array
    {
        return $master;
    }

    protected function previewSessionKey(): string
    {
        return 'grouping_preview_' . $this->tableName();
    }

    protected function csvHeaders(): array
    {
        return array_values($this->datatableColumns());
    }

    // ─── INDEX ────────────────────────────────────────────────────────
    public function index()
    {
        $counts = $this->stagingCounts();

        return view($this->viewPrefix() . '.index', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'columns'      => $this->datatableColumns(),
            'totalRows'    => $this->baseQuery()->count(),
            'newRows'      => $counts['new_rows'] ?? 0,
            'hasCsv'       => $this->hasCsv(),
            'hasSap'       => $this->hasSap(),
            'hasGetFromSap'=> $this->hasGetFromSap(),
            'hasQr'        => $this->hasQr(),
        ]);
    }

    // ─── DATATABLE ────────────────────────────────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery();
        return DataTables::query($query)->addIndexColumn()->make(true);
    }

    // ─── CREATE form ──────────────────────────────────────────────────
    public function create()
    {
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => null,
        ]);
    }

    // ─── STORE ────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->storeValidation());

        $data = array_merge($this->storeData($request), [
            'company_id' => $this->companyId(),
            'created_by' => $this->userName(),
            'updated_by' => $this->userName(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->tableName())->insert($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE,
            "Created {$this->resourceName()}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' added successfully.');
    }

    // ─── EDIT form ───────────────────────────────────────────────────
    public function edit(int $id)
    {
        $item = $this->baseQuery()->where('id', $id)->first();
        abort_unless($item, 404);
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => $item,
        ]);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────
    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->storeValidation());

        $data = array_merge($this->storeData($request), [
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ]);

        $this->baseQuery()->where('id', $id)->update($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE,
            "Updated {$this->resourceName()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' updated successfully.');
    }

    // ─── DESTROY ──────────────────────────────────────────────────────
    public function destroy(int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->baseQuery()->where('id', $id)->delete();
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_DELETE,
            "Deleted {$this->resourceName()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' deleted.');
    }

    // ─── UPLOAD form ──────────────────────────────────────────────────
    public function upload()
    {
        $view = view()->exists($this->viewPrefix() . '.upload')
            ? $this->viewPrefix() . '.upload'
            : 'admin.grouping._shared.upload';

        return view($view, [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
        ]);
    }

    // ─── PREVIEW uploaded CSV ─────────────────────────────────────────
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

        $headers = array_map('trim', array_shift($rows));
        $preview = [];
        $errors  = [];
        $valid   = [];

        foreach ($rows as $i => $row) {
            if (count($row) === 1 && empty(trim($row[0]))) continue;

            $combined = @array_combine($headers, array_pad($row, count($headers), ''));
            if ($combined === false) {
                $combined = [];
                $cols = array_keys($this->datatableColumns());
                foreach ($cols as $idx => $col) {
                    $combined[$col] = $row[$idx] ?? '';
                }
            }

            $mapped = $this->mapCsvRow($combined, $i + 2);
            if ($mapped === null) continue;

            $error = $this->validateRow($mapped);
            if ($error) {
                $errors[] = "Row " . ($i + 2) . ": {$error}";
            } else {
                $valid[] = $mapped;
            }

            if (count($preview) < 100) {
                $preview[] = ['data' => $mapped, 'error' => $error];
            }
        }

        session([$this->previewSessionKey() => $valid]);

        $view = view()->exists($this->viewPrefix() . '.preview')
            ? $this->viewPrefix() . '.preview'
            : 'admin.grouping._shared.preview';

        return view($view, [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'preview'      => $preview,
            'validCount'   => count($valid),
            'errorCount'   => count($errors),
            'errors'       => array_slice($errors, 0, 20),
            'headers'      => array_keys($this->datatableColumns()),
        ]);
    }

    protected function mapCsvRow(array $row, int $lineNum): ?array
    {
        $cols = array_keys($this->datatableColumns());
        $mapped = [];
        $rowVals = array_values($row);

        foreach ($cols as $idx => $col) {
            $mapped[$col] = trim($rowVals[$idx] ?? $row[$col] ?? '');
        }

        return $mapped;
    }

    protected function validateRow(array $row): ?string
    {
        foreach ($this->storeValidation() as $field => $rules) {
            if (str_contains($rules, 'required') && empty($row[$field])) {
                return "Field '{$field}' is required.";
            }
        }
        return null;
    }

    // ─── SAVE uploaded data ───────────────────────────────────────────
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
                $row['company_id'] = $this->companyId();
                $row['created_by'] = $this->userName();
                $row['updated_by'] = $this->userName();
                $row['created_at'] = now();
                $row['updated_at'] = now();

                DB::table($this->tableName())->insert($row);
            }
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

    // ─── CANCEL preview ───────────────────────────────────────────────
    public function cancelUpload(): RedirectResponse
    {
        session()->forget($this->previewSessionKey());
        return redirect()->route($this->routePrefix() . '.index');
    }

    // ─── GENERATE CSV template ────────────────────────────────────────
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

    // ─── EXPORT actual grouping data ──────────────────────────────────
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

    // ─── SAP TWO-STEP FLOW ────────────────────────────────────────────

    /** STEP 1 — "Get All Data From SAP" */
    public function getFromSap(): JsonResponse
    {
        $sap = $this->sapConfig();
        if (! $sap) {
            return $this->jsonError('SAP sync is not configured for this module.');
        }
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $service = app(\App\Services\SapService::class);
        $ctx     = $service->context($this->companyId());

        $filters = [];
        foreach ($sap['filters'] as $k => $v) {
            $filters[$k] = is_string($v) ? strtr($v, [
                '{country_code}' => $ctx['country_code'],
                '{company_code}' => $ctx['company_code'],
                '{plant_code}'   => $ctx['config']?->plant_code ?? '',
                '{estate_code}'  => $ctx['config']?->estate_code ?? '',
                '{estate_name}'  => $ctx['config']?->estate_name ?? '',
                '{profile_name}' => $ctx['config']?->profile_name ?? '',
            ]) : $v;
        }

        $result = $service->fetchMasterData($sap['urn'], $filters, $ctx, $sap['columns']);

        if ($result['status_code'] !== 200) {
            return $this->jsonError('Error requesting data from SAP (HTTP ' . $result['status_code'] . ').');
        }
        if (empty($result['data'])) {
            return $this->jsonError('SAP returned no data for ' . strtolower($this->resourceName()) . '.');
        }

        DB::transaction(function () use ($sap, $result, $ctx) {
            if (Schema::hasColumn($sap['staging'], 'company_code')) {
                DB::table($sap['staging'])->where('company_code', $ctx['company_code'])->delete();
            } else {
                DB::table($sap['staging'])->truncate();
            }

            foreach ($result['data'] as $item) {
                $row = [];
                if (Schema::hasColumn($sap['staging'], 'company_code')) $row['company_code'] = $ctx['company_code'];
                if (Schema::hasColumn($sap['staging'], 'country_code')) $row['country_code'] = $ctx['country_code'];
                if (Schema::hasColumn($sap['staging'], 'country_no'))   $row['country_no']   = $ctx['country_no'];
                if (Schema::hasColumn($sap['staging'], 'fetched_at'))    $row['fetched_at']    = now();

                foreach ($sap['columns'] as $col) {
                    if (Schema::hasColumn($sap['staging'], $col)) {
                        $row[$col] = $item[$col] ?? null;
                    }
                }
                DB::table($sap['staging'])->insert($row);
            }
        });

        AuditService::log(
            AuditService::TYPE_SAP_SYNC, AuditService::ACTION_CREATE,
            "Fetched " . count($result['data']) . " " . $this->resourceName() . " records from SAP into staging"
        );

        return $this->jsonSuccess(
            count($result['data']) . ' ' . $this->resourceName() . ' records fetched from SAP into staging.',
            $this->stagingCounts()
        );
    }

    /** STEP 2 — "Refresh Master Data From SAP" */
    public function refreshMasterDataFromStaging(): JsonResponse
    {
        $sap = $this->sapConfig();
        if (! $sap) {
            return $this->jsonError('SAP sync is not configured for this module.');
        }
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $service = app(\App\Services\SapService::class);
        $ctx     = $service->context($this->companyId());

        $stgQuery = DB::table($sap['staging']);
        if (Schema::hasColumn($sap['staging'], 'company_code')) {
            $stgQuery->where('company_code', $ctx['company_code']);
        }
        if (! empty($sap['staging_conditions'])) {
            foreach ($sap['staging_conditions'] as $field => $val) {
                if ($val === '!= ""') {
                    $stgQuery->whereNotNull($field)->where($field, '!=', '');
                } else {
                    $stgQuery->where($field, $val);
                }
            }
        }

        $stagingRows = $stgQuery->get();
        if ($stagingRows->isEmpty()) {
            return $this->jsonError('No staged data found. Run "Get All Data From SAP" first.');
        }

        try {
            $inserted = 0;
            DB::transaction(function () use ($sap, $stagingRows, &$inserted) {
                $this->baseQuery()->delete();

                foreach ($stagingRows as $stg) {
                    $stgArr = (array) $stg;
                    $row = [
                        'company_id' => $this->companyId(),
                        'created_by' => $this->userName(),
                        'updated_by' => $this->userName(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    foreach ($sap['mapping'] as $col => $sapField) {
                        if ($sapField === null) continue;
                        $row[$col] = $stgArr[$sapField] ?? null;
                    }
                    $row = $this->transformSapRow($row, $stgArr);
                    DB::table($this->tableName())->insert($row);
                    $inserted++;
                }
            });

            AuditService::log(
                AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE,
                "Refreshed " . $this->resourceName() . " grouping from staging ({$inserted} rows)"
            );

            return $this->jsonSuccess(
                "{$inserted} " . $this->resourceName() . ' records refreshed from SAP staging.',
                $this->stagingCounts()
            );
        } catch (\Throwable $e) {
            return $this->jsonError('Refresh failed, transaction cancelled: ' . $e->getMessage());
        }
    }

    public function stagingInfo(): JsonResponse
    {
        return $this->jsonSuccess('OK', $this->stagingCounts());
    }

    protected function stagingCounts(): array
    {
        $sap = $this->sapConfig();
        $newRows = 0;

        if ($sap && class_exists('\App\Services\SapService')) {
            try {
                $sapService = app(\App\Services\SapService::class);
                if (method_exists($sapService, 'context')) {
                    $ctx = $sapService->context($this->companyId());
                    if (Schema::hasTable($sap['staging'])) {
                        $q = DB::table($sap['staging']);
                        if (Schema::hasColumn($sap['staging'], 'company_code')) {
                            $q->where('company_code', $ctx['company_code']);
                        }
                        if (! empty($sap['staging_conditions'])) {
                            foreach ($sap['staging_conditions'] as $field => $val) {
                                if ($val === '!= ""') {
                                    $q->whereNotNull($field)->where($field, '!=', '');
                                } else {
                                    $q->where($field, $val);
                                }
                            }
                        }
                        $newRows = $q->count();
                    }
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        return [
            'current_rows' => $this->baseQuery()->count(),
            'new_rows'     => $newRows,
        ];
    }
}
