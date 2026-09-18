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
 * Task / TPH master (m_tph) — company-scoped harvesting collection points.
 * CRUD with cascading Estate > Division > Block selection and a business
 * rule: the sum of tph_palm_total within a block must not exceed the
 * block's total_palm. No SAP. QR content = tph_code (deferred).
 */
class TphController extends BaseGroupingController
{
    use HandlesCsvMaster;
    use \App\Http\Controllers\Admin\Masters\Concerns\HasRowQr;

    protected function qrValueColumn(): string { return 'tph_code'; }
    protected function qrCaptionColumns(): array
    {
        return ['Estate' => 'estate_code', 'Division' => 'division_code', 'Block' => 'block_code', 'Platform' => 'section_code'];
    }

    protected function tableName(): string    { return 'm_tph'; }
    protected function hasEdit(): bool { return true; }

    protected function resourceName(): string { return 'Task (TPH)'; }
    protected function viewPrefix(): string   { return 'admin.masters.tph'; }
    protected function routePrefix(): string  { return 'masters.tph'; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'    => 'Estate',
            'division_code'  => 'Division',
            'block_code'     => 'Block',
            'section_code'   => 'Platform',
            'tph_code'       => 'TPH Code',
            'tph_palm_total' => 'Palm Total',
            'tph_valid'      => 'Valid Period',
        ];
    }

    // Not used directly (store/update overridden) but required by the base.
    protected function storeValidation(): array
    {
        return $this->tphValidation();
    }

    protected function storeData(Request $request): array
    {
        return $this->tphData($request);
    }

    // ── DataTable (add computed valid-period column) ──────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery();
        return DataTables::query($query)
            ->addIndexColumn()
            ->addColumn('tph_valid', function ($row) {
                $from = $row->valid_from ? \Carbon\Carbon::parse($row->valid_from)->format('d.m.Y') : '-';
                $to   = $row->valid_to   ? \Carbon\Carbon::parse($row->valid_to)->format('d.m.Y')   : '-';
                return "{$from} — {$to}";
            })
            ->rawColumns(['tph_valid'])
            ->make(true);
    }

    // ── Form data (cascading dropdown sources) ────────────────────────────────
    protected function formData(): array
    {
        $cid = $this->companyId();
        return [
            'estates'   => DB::table('m_estate')->where('company_id', $cid)
                            ->orderBy('estate_code')->get(['estate_code', 'estate_name']),
            'divisions' => DB::table('m_division')->where('company_id', $cid)
                            ->orderBy('division_code')->get(['estate_code', 'division_code', 'division_name']),
            'blocks'    => DB::table('m_block')->where('company_id', $cid)
                            ->orderBy('block_code')->get(['estate_code', 'division_code', 'block_code', 'block_name', 'total_palm']),
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
        $request->validate($this->tphValidation());

        if ($err = $this->palmTotalExceeded($request)) {
            return back()->withInput()->with('error', $err);
        }

        $data = array_merge($this->tphData($request), [
            'company_id' => $this->companyId(),
            'created_by' => $this->userName(),
            'updated_by' => $this->userName(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->tableName())->insert($data);
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE, 'Created TPH ' . $request->tph_code);

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', 'Task (TPH) added successfully.');
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->tphValidation());

        if ($err = $this->palmTotalExceeded($request, $id)) {
            return back()->withInput()->with('error', $err);
        }

        $data = array_merge($this->tphData($request), [
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ]);

        $this->baseQuery()->where('id', $id)->update($data);
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE, "Updated TPH #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', 'Task (TPH) updated successfully.');
    }

    // ── Validation + normalized data ──────────────────────────────────────────
    protected function tphValidation(): array
    {
        return [
            'estate_code'    => 'required|string|max:20',
            'division_code'  => 'required|string|max:50',
            'block_code'     => 'required|string|max:50',
            'section_code'   => 'nullable|string|max:50',
            'tph_code'       => 'required|string|max:50',
            'tph_palm_total' => 'required|integer|min:0',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
            'valid_from'     => 'nullable|date',
            'valid_to'       => 'nullable|date',
        ];
    }

    protected function tphData(Request $request): array
    {
        return [
            'estate_code'    => trim($request->estate_code),
            'division_code'  => trim($request->division_code),
            'block_code'     => trim($request->block_code),
            'section_code'   => $request->section_code ? trim($request->section_code) : null,
            'tph_code'       => strtoupper(trim($request->tph_code)),
            'tph_palm_total' => (int) $request->tph_palm_total,
            'latitude'       => $request->latitude !== null && $request->latitude !== '' ? (float) $request->latitude : null,
            'longitude'      => $request->longitude !== null && $request->longitude !== '' ? (float) $request->longitude : null,
            'valid_from'     => $request->valid_from ?: null,
            'valid_to'       => $request->valid_to ?: null,
        ];
    }

    // ── CSV (11 columns, matches CI4) ─────────────────────────────────────────
    protected function csvHeaders(): array
    {
        return [
            'estate_code', 'division_code', 'block_code', 'section_code',
            'tph_code', 'tph_palm_total', 'valid_from', 'valid_to',
            'latitude', 'longitude',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = strtoupper(trim($row['tph_code'] ?? ''));
        if ($code === '' && trim($row['block_code'] ?? '') === '') return null;

        return [
            'estate_code'    => trim($row['estate_code'] ?? ''),
            'division_code'  => trim($row['division_code'] ?? ''),
            'block_code'     => trim($row['block_code'] ?? ''),
            'section_code'   => trim($row['section_code'] ?? '') ?: null,
            'tph_code'       => $code,
            'tph_palm_total' => (int) ($row['tph_palm_total'] ?? 0),
            'valid_from'     => $this->normalizeDate($row['valid_from'] ?? null),
            'valid_to'       => $this->normalizeDate($row['valid_to'] ?? null),
            'latitude'       => ($row['latitude'] ?? '') !== '' ? (float) $row['latitude'] : null,
            'longitude'      => ($row['longitude'] ?? '') !== '' ? (float) $row['longitude'] : null,
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if ($row['estate_code'] === '')   return 'Estate is required.';
        if ($row['division_code'] === '') return 'Division is required.';
        if ($row['block_code'] === '')    return 'Block is required.';
        if ($row['tph_code'] === '')      return 'TPH Code is required.';
        return null;
    }

    /** Normalize CI4 date quirks (31.12.9999 -> 9999-12-31, dd.mm.yyyy). */
    protected function normalizeDate(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return $v; // assume already Y-m-d
    }

    /** Export uses real DB columns (datatableColumns has a computed tph_valid). */
    public function exportMasterData(): StreamedResponse
    {
        $headers  = $this->csvHeaders();
        $rows     = DB::table('m_tph')->where('company_id', $this->companyId())->get();
        $filename = 'task_tph_' . now()->format('Y-m-d') . '.csv';

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

    /**
     * Business rule: total palm assigned to TPH within a block must not
     * exceed the block's total_palm. Returns an error string or null.
     */
    protected function palmTotalExceeded(Request $request, ?int $excludeId = null): ?string
    {
        $block = DB::table('m_block')
            ->where('company_id', $this->companyId())
            ->where('estate_code', trim($request->estate_code))
            ->where('division_code', trim($request->division_code))
            ->where('block_code', trim($request->block_code))
            ->first();

        // If the block or its capacity is unknown, skip the check.
        if (! $block || $block->total_palm === null) {
            return null;
        }

        $existing = DB::table('m_tph')
            ->where('company_id', $this->companyId())
            ->where('estate_code', trim($request->estate_code))
            ->where('division_code', trim($request->division_code))
            ->where('block_code', trim($request->block_code))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('tph_palm_total');

        $newTotal = (int) $existing + (int) $request->tph_palm_total;

        if ($newTotal > (int) $block->total_palm) {
            return "Total palm ({$newTotal}) exceeds block capacity ({$block->total_palm}) for block "
                . "{$block->block_code}. Please reduce the palm total.";
        }

        return null;
    }
    public function index()
    {
        $cid = $this->companyId();
        
        // Calculate total_palm_exceed banner matching CI3
        $blocks = DB::table('m_block as b')
            ->leftJoin('m_tph as t', function ($join) {
                $join->on('t.company_id', '=', 'b.company_id')
                     ->on('t.estate_code', '=', 'b.estate_code')
                     ->on('t.division_code', '=', 'b.division_code')
                     ->on('t.block_code', '=', 'b.block_code');
            })
            ->where('b.company_id', $cid)
            ->groupBy('b.block_code', 'b.block_name', 'b.division_code', 'b.estate_code', 'b.total_palm')
            ->select([
                'b.block_code',
                'b.block_name',
                'b.division_code as block_division_code',
                'b.estate_code as block_estate_code',
                'b.total_palm as block_total_palm',
                DB::raw('COALESCE(SUM(t.tph_palm_total), 0) as ttl_plm'),
            ])
            ->get();

        $totalPalmExceed = [];
        foreach ($blocks as $blk) {
            if ($blk->block_total_palm !== null && $blk->ttl_plm > $blk->block_total_palm) {
                $totalPalmExceed[] = $blk;
            }
        }

        return view($this->viewPrefix() . '.index', [
            'resourceName'    => $this->resourceName(),
            'routePrefix'     => $this->routePrefix(),
            'columns'         => $this->datatableColumns(),
            'totalRows'       => $this->baseQuery()->count(),
            'newRows'         => 0,
            'hasEdit'         => $this->hasEdit(),
            'hasCsv'          => $this->hasCsv(),
            'hasSap'          => false,
            'hasGetFromSap'   => false,
            'hasQr'           => $this->hasQr(),
            'totalPalmExceed' => $totalPalmExceed,
        ]);
    }

    /**
     * Generate QR code for selected tasks (matching CI3).
     * Format: EST:{estate};BLOCK:{block};PLATFORM:{platform};TASK:{task}
     */
    public function generateQr(Request $request)
    {
        $request->validate([
            'tph_id'   => 'required|array|min:1',
            'tph_id.*' => 'required|integer',
        ]);

        $taskIds = $request->input('tph_id');

        $tasks = DB::table('m_tph as t')
            ->leftJoin('m_block as b', function ($join) {
                $join->on('t.company_id', '=', 'b.company_id')
                     ->on('t.estate_code', '=', 'b.estate_code')
                     ->on('t.division_code', '=', 'b.division_code')
                     ->on('t.block_code', '=', 'b.block_code');
            })
            ->where('t.company_id', $this->companyId())
            ->whereIn('t.id', $taskIds)
            ->orderByDesc('t.id')
            ->select([
                't.id',
                't.estate_code',
                't.division_code',
                't.block_code',
                'b.block_name',
                't.section_code',
                't.tph_code',
                't.tph_palm_total',
            ])
            ->get()
            ->map(function ($t) {
                $t->qr_text = "EST:{$t->estate_code};BLOCK:{$t->block_code};PLATFORM:{$t->section_code};TASK:{$t->tph_code}";
                return $t;
            });

        return view($this->viewPrefix() . '.print_qr', [
            'tasks'        => $tasks,
            'resourceName' => $this->resourceName(),
        ]);
    }
}
