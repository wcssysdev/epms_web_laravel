<?php

namespace App\Http\Controllers\Admin\Masters\Concerns;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds CSV upload/preview/save + template/export to a company-scoped CRUD
 * master (a BaseGroupingController subclass). No SAP. Reuses the shared
 * upload/preview views under admin.masters._shared.
 *
 * The host controller must provide:
 *   - tableName(), resourceName(), routePrefix(), viewPrefix(), companyId()
 *   - datatableColumns() [db_col => label]  (used for export column order)
 *   - csvHeaders(): array                   (template/export header row)
 *   - mapCsvRow(array $assoc, int $rowNum): ?array  (CSV row -> DB insert array)
 *   - validateRow(array $mapped): ?string   (per-row validation, null = ok)
 *
 * When useReplaceAll() returns true (e.g. OPH/FDN cards), saving wipes the
 * company's existing rows first (mirrors CI4 replace-all behaviour).
 */
trait HandlesCsvMaster
{
    /** Session key holding validated preview rows. */
    protected function csvPreviewKey(): string
    {
        return 'csv_preview_' . $this->tableName();
    }

    /** CSV buttons are shown for masters using this trait. */
    protected function hasCsv(): bool
    {
        return true;
    }

    /** Override to true for masters that replace all rows on import. */
    protected function useReplaceAll(): bool
    {
        return false;
    }

    // ── UPLOAD form ───────────────────────────────────────────────────────────
    public function upload()
    {
        return view('admin.masters._shared.upload', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
        ]);
    }

    // ── PREVIEW ────────────────────────────────────────────────────────────────
    public function preview(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $lines = file($request->file('csv_file')->getRealPath());
        if (! $lines) {
            return back()->with('error', 'CSV file is empty.');
        }

        // Support both ',' and ';' delimiters (auto-detect from header line).
        $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
        $rows = array_map(fn ($l) => str_getcsv($l, $delimiter), $lines);

        $headers = array_map('trim', array_shift($rows));
        $preview = [];
        $errors  = [];
        $valid   = [];

        foreach ($rows as $i => $row) {
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;

            $assoc  = array_combine($headers, array_pad($row, count($headers), ''));
            $mapped = $this->mapCsvRow($assoc, $i + 2);
            if ($mapped === null) continue;

            $error = $this->validateRow($mapped);
            if ($error) {
                $errors[] = 'Row ' . ($i + 2) . ": {$error}";
            } else {
                $valid[] = $mapped;
            }

            if (count($preview) < 100) {
                $preview[] = ['data' => $mapped, 'error' => $error];
            }
        }

        session([$this->csvPreviewKey() => $valid]);

        return view('admin.masters._shared.preview', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'preview'      => $preview,
            'validCount'   => count($valid),
            'errorCount'   => count($errors),
            'errors'       => array_slice($errors, 0, 20),
            'headers'      => array_keys($this->datatableColumns()),
        ]);
    }

    // ── SAVE ─────────────────────────────────────────────────────────────────
    public function saveUploadedData(): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $rows = session($this->csvPreviewKey(), []);
        if (empty($rows)) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', 'No preview data found. Please upload again.');
        }

        DB::transaction(function () use ($rows) {
            if ($this->useReplaceAll()) {
                DB::table($this->tableName())->where('company_id', $this->companyId())->delete();
            }
            foreach ($rows as $row) {
                $row['company_id'] = $this->companyId();
                $row['created_by'] = $row['created_by'] ?? $this->userName();
                $row['updated_by'] = $this->userName();
                $row['created_at'] = $row['created_at'] ?? now();
                $row['updated_at'] = now();
                DB::table($this->tableName())->insert($row);
            }
        });

        session()->forget($this->csvPreviewKey());

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_CREATE,
            'Uploaded ' . count($rows) . ' rows to ' . $this->resourceName()
        );

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', count($rows) . ' ' . $this->resourceName() . ' records saved successfully.');
    }

    // ── CANCEL ─────────────────────────────────────────────────────────────────
    public function cancelUpload(): RedirectResponse
    {
        session()->forget($this->csvPreviewKey());
        return redirect()->route($this->routePrefix() . '.index');
    }

    // ── EXPORT (actual data) ───────────────────────────────────────────────────
    public function exportMasterData(): StreamedResponse
    {
        $headers  = $this->csvHeaders();
        $cols     = array_keys($this->datatableColumns());
        $rows     = DB::table($this->tableName())->where('company_id', $this->companyId())->get();
        $filename = strtolower(str_replace(' ', '_', $this->resourceName())) . '_' . now()->format('Y-m-d') . '.csv';

        return response()->stream(function () use ($headers, $rows, $cols) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $headers);
            foreach ($rows as $row) {
                $arr = (array) $row;
                fputcsv($h, array_map(fn ($c) => $arr[$c] ?? '', $cols));
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── TEMPLATE (empty) ─────────────────────────────────────────────────────
    public function generateCsv(): StreamedResponse
    {
        $headers  = $this->csvHeaders();
        $filename = strtolower(str_replace(' ', '_', $this->resourceName())) . '_template.csv';

        return response()->stream(function () use ($headers) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $headers);
            fputcsv($h, array_fill(0, count($headers), ''));
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
