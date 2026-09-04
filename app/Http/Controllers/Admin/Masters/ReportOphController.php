<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use App\Http\Controllers\Admin\Masters\Concerns\HandlesCsvMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

/**
 * Report OPH / Master OPH (m_report_oph) — company-scoped rate configuration
 * per (period, division, block): basis, gandeng, premi rates, brondolan
 * rates, HK rate. CRUD, no SAP.
 */
class ReportOphController extends BaseGroupingController
{
    use HandlesCsvMaster;

    protected function tableName(): string    { return 'm_report_oph'; }
    protected function resourceName(): string { return 'Master OPH'; }
    protected function viewPrefix(): string   { return 'admin.masters.report_oph'; }
    protected function routePrefix(): string  { return 'masters.report_oph'; }

    protected function datatableColumns(): array
    {
        return [
            'period'           => 'Period',
            'division_code'    => 'Division',
            'block_code'       => 'Block',
            'block_name'       => 'Block Description',
            'basis'            => 'Basis',
            'gandeng'          => 'Gandeng',
            'premi_basis'      => 'Premi > Basis',
            'premi_non_basis'  => 'Premi < Basis',
            'brondolan_rate_1' => 'Brondolan 1',
            'brondolan_rate_2' => 'Brondolan 2',
            'hk_rate'          => 'HK Rate',
        ];
    }

    protected function storeValidation(): array
    {
        return $this->ophValidation();
    }

    protected function storeData(Request $request): array
    {
        return $this->ophData($request);
    }

    // ── DataTable (join block name) ───────────────────────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        $query = DB::table('m_report_oph as o')
            ->leftJoin('m_block as b', function ($j) {
                $j->on('b.company_id', '=', 'o.company_id')
                  ->on('b.estate_code', '=', 'o.estate_code')
                  ->on('b.division_code', '=', 'o.division_code')
                  ->on('b.block_code', '=', 'o.block_code');
            })
            ->where('o.company_id', $this->companyId())
            ->select('o.*', 'b.block_name');

        return DataTables::query($query)->addIndexColumn()->make(true);
    }

    // ── Form data (dropdown sources) ──────────────────────────────────────────
    protected function formData(): array
    {
        $cid = $this->companyId();
        return [
            'divisions' => DB::table('m_division')->where('company_id', $cid)
                            ->orderBy('division_code')->get(['estate_code', 'division_code', 'division_name']),
            // Harvestable, planted blocks only (matches CI4 filter)
            'blocks'    => DB::table('m_block')->where('company_id', $cid)
                            ->where('is_planted', true)
                            ->orderBy('block_code')->get(['estate_code', 'division_code', 'block_code', 'block_name']),
            'estateCode' => $this->estateCode(),
        ];
    }

    public function create()
    {
        return view($this->viewPrefix() . '.form', array_merge([
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => null,
        ], $this->formData()));
    }

    public function edit(int $id)
    {
        $item = $this->baseQuery()->where('id', $id)->first();
        abort_unless($item, 404);
        return view($this->viewPrefix() . '.form', array_merge([
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => $item,
        ], $this->formData()));
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->ophValidation());

        $data = array_merge($this->ophData($request), [
            'company_id'  => $this->companyId(),
            'estate_code' => $this->estateCode(),
            'created_by'  => $this->userName(),
            'updated_by'  => $this->userName(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table($this->tableName())->insert($data);
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE, 'Created Master OPH rate');

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', 'Master OPH rate added successfully.');
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->ophValidation());

        $data = array_merge($this->ophData($request), [
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ]);

        $this->baseQuery()->where('id', $id)->update($data);
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE, "Updated Master OPH rate #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', 'Master OPH rate updated successfully.');
    }

    // ── Validation + data ─────────────────────────────────────────────────────
    protected function ophValidation(): array
    {
        return [
            'period'           => 'required|date',
            'division_code'    => 'required|string|max:50',
            'block_code'       => 'required|string|max:50',
            'basis'            => 'required|numeric|min:0',
            'gandeng'          => 'required|numeric|min:0',
            'premi_basis'      => 'required|numeric|min:0',
            'premi_non_basis'  => 'required|numeric|min:0',
            'brondolan_rate_1' => 'required|numeric|min:0',
            'brondolan_rate_2' => 'required|numeric|min:0',
            'hk_rate'          => 'required|numeric|min:0',
        ];
    }

    protected function ophData(Request $request): array
    {
        return [
            'period'           => $request->period,
            'division_code'    => trim($request->division_code),
            'block_code'       => trim($request->block_code),
            'basis'            => (float) $request->basis,
            'gandeng'          => (float) $request->gandeng,
            'premi_basis'      => (float) $request->premi_basis,
            'premi_non_basis'  => (float) $request->premi_non_basis,
            'brondolan_rate_1' => (float) $request->brondolan_rate_1,
            'brondolan_rate_2' => (float) $request->brondolan_rate_2,
            'hk_rate'          => (float) $request->hk_rate,
        ];
    }

    // ── CSV (11 rate columns) ──────────────────────────────────────────────────
    protected function csvHeaders(): array
    {
        return [
            'period', 'division_code', 'block_code', 'basis', 'gandeng',
            'premi_basis', 'premi_non_basis', 'brondolan_rate_1',
            'brondolan_rate_2', 'hk_rate',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        if (trim($row['block_code'] ?? '') === '' && trim($row['period'] ?? '') === '') return null;
        return [
            'estate_code'      => $this->estateCode(),
            'period'           => $this->normalizeDate($row['period'] ?? null),
            'division_code'    => trim($row['division_code'] ?? ''),
            'block_code'       => trim($row['block_code'] ?? ''),
            'basis'            => (float) ($row['basis'] ?? 0),
            'gandeng'          => (float) ($row['gandeng'] ?? 0),
            'premi_basis'      => (float) ($row['premi_basis'] ?? 0),
            'premi_non_basis'  => (float) ($row['premi_non_basis'] ?? 0),
            'brondolan_rate_1' => (float) ($row['brondolan_rate_1'] ?? 0),
            'brondolan_rate_2' => (float) ($row['brondolan_rate_2'] ?? 0),
            'hk_rate'          => (float) ($row['hk_rate'] ?? 0),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if ($row['period'] === null || $row['period'] === '') return 'Period is required.';
        if ($row['division_code'] === '') return 'Division is required.';
        if ($row['block_code'] === '')    return 'Block is required.';
        return null;
    }

    protected function normalizeDate(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return $v;
    }

    /** Export uses real DB columns (datatableColumns has a joined block_name). */
    public function exportMasterData(): StreamedResponse
    {
        $headers  = $this->csvHeaders();
        $rows     = DB::table('m_report_oph')->where('company_id', $this->companyId())->get();
        $filename = 'master_oph_' . now()->format('Y-m-d') . '.csv';

        return response()->stream(function () use ($headers, $rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $headers);
            foreach ($rows as $row) {
                $arr = (array) $row;
                fputcsv($h, array_map(fn ($c) => $arr[$c] ?? '', $headers));
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
