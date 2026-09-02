<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
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
        $totalRows  = $this->baseQuery()->count();
        $lastUpdate = DB::table('master_data_log')
            ->where('company_id', $this->companyId())
            ->where('table_name', $this->tableName())
            ->orderByDesc('last_updated_at')
            ->value('last_updated_at');

        return view($this->viewPrefix() . '.index', [
            'resourceName' => $this->resourceName(),
            'totalRows'    => $totalRows,
            'lastUpdate'   => $lastUpdate,
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

    // ── REPLACE master data from SAP ──────────────────────────────────────────
    public function replaceMasterData(): JsonResponse
    {
        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        try {
            $data = $this->fetchFromSap();

            if (empty($data)) {
                return $this->jsonError('No data returned from SAP.');
            }

            DB::transaction(function () use ($data) {
                // Delete existing data for this company
                DB::table($this->tableName())
                    ->where('company_id', $this->companyId())
                    ->delete();

                foreach ($data as $row) {
                    $row['company_id'] = $this->companyId();
                    $row['created_by'] = $this->userName();
                    $row['created_at'] = now();
                    $row['updated_by'] = $this->userName();
                    $row['updated_at'] = now();
                    DB::table($this->tableName())->insert($row);
                }
                $this->logMasterDataUpdate(count($data));
            });

            AuditService::log(
                AuditService::TYPE_MASTER,
                AuditService::ACTION_UPDATE,
                "Replaced " . $this->resourceName() . " from SAP (" . count($data) . " rows)"
            );

            return $this->jsonSuccess(
                count($data) . ' ' . $this->resourceName() . ' records replaced from SAP.',
                ['count' => count($data)]
            );

        } catch (\Exception $e) {
            return $this->jsonError('SAP sync failed: ' . $e->getMessage());
        }
    }

    // ── GET master data info (last refresh etc) ───────────────────────────────
    public function getMasterData(): JsonResponse
    {
        $log = DB::table('master_data_log')
            ->where('company_id', $this->companyId())
            ->where('table_name', $this->tableName())
            ->orderByDesc('last_updated_at')
            ->first();

        return $this->jsonSuccess('OK', [
            'table'          => $this->tableName(),
            'total'          => $this->baseQuery()->count(),
            'last_refresh'   => $log?->last_refresh_at,
            'last_updated'   => $log?->last_updated_at,
        ]);
    }

    // ── REFRESH master data info ──────────────────────────────────────────────
    public function refreshMasterDataInfo(): JsonResponse
    {
        return $this->getMasterData();
    }

    // ── GENERATE CSV template ─────────────────────────────────────────────────
    public function generateCsv(): Response
    {
        $headers = $this->csvHeaders();
        $filename = strtolower(str_replace(' ', '_', $this->resourceName())) . '_template.csv';

        $callback = function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            // Add one sample row with empty values
            fputcsv($handle, array_fill(0, count($headers), ''));
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── FETCH from SAP (override in child if needed) ──────────────────────────
    protected function fetchFromSap(): array
    {
        // Default: return empty, child controllers override with SAP call
        return [];
    }

    // ── Log master data update ────────────────────────────────────────────────
    protected function logMasterDataUpdate(int $count): void
    {
        DB::table('master_data_log')->updateOrInsert(
            [
                'company_id' => $this->companyId(),
                'table_name' => $this->tableName(),
            ],
            [
                'last_updated_at'   => now(),
                'last_updated_by'   => $this->userId(),
                'is_replaced'       => true,
            ]
        );
    }
}
