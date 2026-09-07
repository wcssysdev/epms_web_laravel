<?php

namespace App\Http\Controllers\Transaction\Concerns;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import for master-detail transaction screens (CP, FDN). Rows are grouped
 * by a header key (delivery_note); each group becomes one header + N detail
 * lines. Header totals are derived from the group's detail rows. Loaders are
 * not imported (add via the form).
 *
 * The host controller must provide:
 *   - routePrefix(), title(), companyId(), estateCode(), userName(), generateId()
 *   - csvHeaders(): array                       (template/columns, in order)
 *   - csvGroupKey(): string                     (column that groups rows, e.g. 'delivery_note')
 *   - csvBuildHeader(array $first, array $details): array  (header row incl. type/flags/totals)
 *   - csvBuildDetail(array $row): ?array         (one detail row, or null to skip)
 *   - csvValidateGroup(string $key, array $rows): ?string  (per-group validation)
 *   - headerModelClass(): string
 *   - persistDetails(string $headerId, array $details): void
 */
trait HandlesMasterDetailCsv
{
    protected function csvPreviewKey(): string
    {
        return 'md_csv_preview_' . $this->routePrefix();
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

        // Parse rows into associative + group by header key.
        $key    = $this->csvGroupKey();
        $groups = [];
        $rowErrors = [];
        foreach ($rows as $i => $row) {
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;
            $assoc = array_combine($headers, array_pad($row, count($headers), ''));
            $gk    = trim((string) ($assoc[$key] ?? ''));
            if ($gk === '') {
                $rowErrors[] = 'Row ' . ($i + 2) . ": {$key} is required for grouping.";
                continue;
            }
            $groups[$gk][] = $assoc;
        }

        $preview = $errors = $validGroups = [];
        foreach ($rowErrors as $e) $errors[] = $e;

        foreach ($groups as $gk => $groupRows) {
            $error = $this->csvValidateGroup($gk, $groupRows);
            if ($error) {
                $errors[] = "Group '{$gk}': {$error}";
                continue;
            }

            $details = [];
            foreach ($groupRows as $r) {
                $d = $this->csvBuildDetail($r);
                if ($d !== null) $details[] = $d;
            }
            if ($details === []) {
                $errors[] = "Group '{$gk}': no valid detail lines.";
                continue;
            }

            $header = $this->csvBuildHeader($groupRows[0], $details);
            $validGroups[] = ['header' => $header, 'details' => $details, 'key' => $gk];

            if (count($preview) < 100) {
                $preview[] = [
                    'data'  => [
                        $this->csvGroupKey() => $gk,
                        'division_code'      => $header['division_code'] ?? '',
                        'lines'              => count($details),
                        'total_bunches'      => $header['total_bunches'] ?? ($header['total_customer_qty'] ?? ''),
                    ],
                    'error' => null,
                ];
            }
        }

        session([$this->csvPreviewKey() => $validGroups]);

        return view('transaction._shared.import_preview', [
            'resourceName' => $this->title(),
            'routePrefix'  => $this->routePrefix(),
            'preview'      => $preview,
            'validCount'   => count($validGroups),
            'errorCount'   => count($errors),
            'errors'       => array_slice($errors, 0, 30),
            'groupKey'     => $this->csvGroupKey(),
        ]);
    }

    // ── SAVE ─────────────────────────────────────────────────────────────────
    public function saveUploadedData(): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $groups = session($this->csvPreviewKey(), []);
        if (empty($groups)) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', 'No preview data found. Please upload again.');
        }

        $model = $this->headerModelClass();
        $saved = 0;

        DB::transaction(function () use ($groups, $model, &$saved) {
            foreach ($groups as $g) {
                $id  = $this->generateId();
                $row = array_merge($g['header'], [
                    'id'         => $id,
                    'company_id' => $this->companyId(),
                    'created_by' => $this->userName(),
                    'updated_by' => $this->userName(),
                ]);
                $model::create($row);
                $this->persistDetails($id, $g['details']);
                $saved++;
                usleep(1500); // keep time-based varchar ids unique
            }
        });

        session()->forget($this->csvPreviewKey());

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Imported {$saved} {$this->title()} records from CSV");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', "{$saved} {$this->title()} records imported successfully.");
    }

    // ── CANCEL ─────────────────────────────────────────────────────────────────
    public function cancelUpload(): RedirectResponse
    {
        session()->forget($this->csvPreviewKey());
        return redirect()->route($this->routePrefix() . '.index');
    }

    // ── TEMPLATE ─────────────────────────────────────────────────────────────
    public function generateCsv(): StreamedResponse
    {
        $headers  = $this->csvHeaders();
        $filename = str_replace([' ', '(', ')'], ['_', '', ''], strtolower($this->title())) . '_import_template.csv';

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
