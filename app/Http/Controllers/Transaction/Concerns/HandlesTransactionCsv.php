<?php

namespace App\Http\Controllers\Transaction\Concerns;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV upload/preview/save + template/export for flat transaction screens
 * (e.g. OPH entry). Reuses the shared master upload/preview views. The host
 * controller (a BaseTransactionController subclass) must provide:
 *
 *   - modelClass(), routePrefix(), companyId(), estateCode(), userName()
 *   - generateId(): ?string           (varchar PK generator, per row)
 *   - csvHeaders(): array             (template/export header row)
 *   - csvColumns(): array             (DB columns to export, in order)
 *   - mapCsvRow(array $assoc, int $n): ?array   (CSV row -> DB insert array)
 *   - validateCsvRow(array $mapped): ?string    (per-row validation)
 *
 * Field capture (GPS/photo/QR) is mobile-only and never part of the import.
 */
trait HandlesTransactionCsv
{
    protected function csvPreviewKey(): string
    {
        return 'tx_csv_preview_' . $this->routePrefix();
    }

    // ── UPLOAD ──────────────────────────────────────────────────────────────
    public function upload()
    {
        return view('admin.masters._shared.upload', [
            'resourceName' => $this->title(),
            'routePrefix'  => $this->routePrefix(),
        ]);
    }

    // ── PREVIEW ─────────────────────────────────────────────────────────────
    public function preview(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $lines = file($request->file('csv_file')->getRealPath());
        if (! $lines) {
            return back()->with('error', 'CSV file is empty.');
        }

        $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
        $rows      = array_map(fn ($l) => str_getcsv($l, $delimiter), $lines);
        $headers   = array_map('trim', array_shift($rows));

        $preview = $errors = $valid = [];

        foreach ($rows as $i => $row) {
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;

            $assoc  = array_combine($headers, array_pad($row, count($headers), ''));
            $mapped = $this->mapCsvRow($assoc, $i + 2);
            if ($mapped === null) continue;

            $error = $this->validateCsvRow($mapped);
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
            'resourceName' => $this->title(),
            'routePrefix'  => $this->routePrefix(),
            'preview'      => $preview,
            'validCount'   => count($valid),
            'errorCount'   => count($errors),
            'errors'       => array_slice($errors, 0, 20),
            'headers'      => $this->csvHeaders(),
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

        $model = $this->modelClass();
        $saved = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, $model, &$saved) {
            foreach ($rows as $row) {
                $row['company_id'] = $this->companyId();
                $row['created_by'] = $this->userName();
                $row['updated_by'] = $this->userName();
                if (($id = $this->generateId()) !== null) {
                    $row['id'] = $id;
                }
                $model::create($row);
                $saved++;
                // Ensure unique varchar ids when generated within the same second.
                usleep(1000);
            }
        });

        session()->forget($this->csvPreviewKey());

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Imported {$saved} {$this->title()} rows from CSV");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', "{$saved} {$this->title()} records imported successfully.");
    }

    // ── CANCEL ─────────────────────────────────────────────────────────────────
    public function cancelUpload(): RedirectResponse
    {
        session()->forget($this->csvPreviewKey());
        return redirect()->route($this->routePrefix() . '.index');
    }

    // ── TEMPLATE (empty) ─────────────────────────────────────────────────────
    public function generateCsv(): StreamedResponse
    {
        $headers  = $this->csvHeaders();
        $filename = str_replace([' ', '(', ')'], ['_', '', ''], strtolower($this->title())) . '_template.csv';

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
