<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\Cp;
use App\Models\Transaction\CpDetail;
use App\Models\Transaction\CpLoader;
use App\Models\Transaction\CoconutOph;
use App\Models\Master\Employee;
use App\Models\Master\Vendor;
use App\Models\Master\ReceivingPoint;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * CP (Coconut) — Checkpoint for coconut Harvesting Chits.
 *
 * Reuses the shared checkpoint tables (t_cp / t_cp_detail / t_cp_loader) with
 * cp_type = 2 and detail_type = 2, but each detail line references a coconut
 * Harvesting Chit (t_coconut_oph) instead of a palm OPH. The chit's nuts_total
 * is stored in the detail's bunches_delivered column and aggregated into the
 * header total_bunches (there is no separate nuts column in t_cp).
 *
 * Distinguished from CP2 (Palm) — which also uses cp_type = 2 — by
 * detail_type = 2 on t_cp_detail. Coconut-enabled companies only.
 */
class CheckpointCoconutController extends BaseController
{
    use \App\Http\Controllers\Transaction\Concerns\GuardsSapIntegration;

    /** Coconut CP maps onto the shared CP2 slot. */
    protected const CP_TYPE     = 2;
    protected const DETAIL_TYPE = 2;

    protected function routePrefix(): string { return 'transactions.checkpoint_coconut'; }
    protected function viewPrefix(): string  { return 'transaction.checkpoint_coconut'; }
    protected function title(): string       { return 'CP (Coconut)'; }

    protected function datatableColumns(): array
    {
        return [
            'created_at'            => 'Date',
            'division_code'         => 'Division',
            'delivery_note'         => 'Delivery Note',
            'total_oph'             => 'HC Count',
            'total_bunches'         => 'Nuts',
            'kerani_kirim_emp_name' => 'Kerani Kirim',
            'integration_status'    => 'SAP Status',
        ];
    }

    // ── INDEX / DATATABLE ──────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);

        return view($this->viewPrefix() . '.index', [
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'columns'     => $this->datatableColumns(),
            'from'        => $from,
            'to'          => $to,
            'hasCsv'      => false,
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        // cp_type = 2 is shared with CP2 (Palm); narrow to coconut checkpoints by
        // requiring at least one coconut detail line (detail_type = 2).
        $query = Cp::query()->actual()->type(self::CP_TYPE)
            ->whereHas('details', fn ($d) => $d->where('detail_type', self::DETAIL_TYPE))
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at');

        return DataTables::eloquent($query)->addIndexColumn()->make(true);
    }

    // ── CREATE / EDIT ──────────────────────────────────────────────────────────
    public function create(): View
    {
        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => null,
            'details'     => collect(),
            'loaders'     => collect(),
        ], $this->formData()));
    }

    public function edit(string $id): View|RedirectResponse
    {
        $item = $this->findCoconutCp($id);
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;

        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => $item,
            'details'     => $item->details()->where('detail_type', self::DETAIL_TYPE)->get(),
            'loaders'     => $item->loaders()->get(),
        ], $this->formData()));
    }

    // ── STORE / UPDATE / DESTROY ────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->validateCp($request);

        $details = $this->cleanDetails($request);
        if ($details === []) {
            return back()->withInput()->with('error', 'Select at least one Harvesting Chit.');
        }
        $loaders = $this->cleanLoaders($request);

        $id = $this->generateId();

        DB::transaction(function () use ($id, $request, $details, $loaders) {
            Cp::create(array_merge($this->mapHeader($request, $details), [
                'id'         => $id,
                'company_id' => $this->companyId(),
                'cp_type'    => self::CP_TYPE,
                'created_by' => $this->userName(),
                'updated_by' => $this->userName(),
            ]));
            $this->saveDetails($id, $details);
            $this->saveLoaders($id, $loaders);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE, "Created {$this->title()} {$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' saved successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = $this->findCoconutCp($id);
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;

        $this->validateCp($request);

        $details = $this->cleanDetails($request);
        if ($details === []) {
            return back()->withInput()->with('error', 'Select at least one Harvesting Chit.');
        }
        $loaders = $this->cleanLoaders($request);

        DB::transaction(function () use ($item, $request, $details, $loaders) {
            $item->update(array_merge($this->mapHeader($request, $details), ['updated_by' => $this->userName()]));
            CpDetail::where('cp_id', $item->id)->delete();
            CpLoader::where('cp_id', $item->id)->delete();
            $this->saveDetails($item->id, $details);
            $this->saveLoaders($item->id, $loaders);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE, "Updated {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = $this->findCoconutCp($id);
        abort_unless($item, 404);
        if ($sap = $this->guardSapDelete($item)) return $sap;

        DB::transaction(function () use ($item) {
            CpDetail::where('cp_id', $item->id)->delete();
            CpLoader::where('cp_id', $item->id)->delete();
            $item->delete();
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE, "Deleted {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' deleted.');
    }

    // ── AJAX: coconut Harvesting Chits eligible to be added to a CP ──────────────
    public function availableChit(Request $request): JsonResponse
    {
        $division = trim((string) $request->query('division_code', ''));

        // Eligible = actual coconut chit for this estate, not already in a CP
        // (detail_type 2), not already in a coconut FDN.
        $inCp = CpDetail::query()->where('detail_type', self::DETAIL_TYPE)->pluck('oph_id')->all();
        $inFdn = DB::table('t_coconut_fdn_detail')->pluck('coconut_oph_id')->all();
        $exclude = array_values(array_unique(array_merge($inCp, $inFdn)));

        $rows = CoconutOph::query()->actual()
            ->where('estate_code', $this->estateCode())
            ->when($division !== '', fn ($q) => $q->where('division_code', $division))
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'oph_card_id', 'division_code', 'block_code', 'tph_code', 'nuts_total']);

        return response()->json(['data' => $rows]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════

    /** Load a CP that is a coconut checkpoint (cp_type 2 + coconut detail). */
    protected function findCoconutCp(string $id): ?Cp
    {
        return Cp::query()->type(self::CP_TYPE)
            ->whereHas('details', fn ($d) => $d->where('detail_type', self::DETAIL_TYPE))
            ->whereKey($id)->first();
    }

    protected function formData(): array
    {
        return [
            'divisions'       => \App\Models\Master\Division::byEstate($this->estateCode())
                                    ->orderBy('division_code')->get(['division_code', 'division_name']),
            'employees'       => Employee::byEstate($this->estateCode())
                                    ->orderBy('employee_code')->get(['employee_code', 'employee_name']),
            'vendors'         => Vendor::orderBy('vendor_code')->get(['vendor_code', 'vendor_name']),
            'receivingPoints' => ReceivingPoint::orderBy('receiving_point_code')
                                    ->get(['receiving_point_code']),
        ];
    }

    protected function validateCp(Request $request): void
    {
        $request->validate([
            'division_code'         => 'required|string|max:50',
            'delivery_note'         => 'nullable|string|max:100',
            'receiving_point_code'  => 'nullable|string|max:50',
            'kerani_kirim_emp_code' => 'nullable|string|max:100',
            'vendor_code'           => 'nullable|string|max:100',
            'license_number'        => 'nullable|string|max:100',
            'seal_code'             => 'nullable|string|max:100',
            'bruto'                 => 'nullable|numeric|min:0',
            'tarra'                 => 'nullable|numeric|min:0',
            'remark'                => 'nullable|string|max:255',
        ]);
    }

    /** Header fields + totals derived from the coconut chit detail lines. */
    protected function mapHeader(Request $request, array $details): array
    {
        $kerani = $request->kerani_kirim_emp_code
            ? Employee::where('employee_code', $request->kerani_kirim_emp_code)->first() : null;
        $vendor = $request->vendor_code
            ? Vendor::where('vendor_code', $request->vendor_code)->first() : null;

        // For coconut, "bunches_delivered" carries nuts; total_bunches = total nuts.
        $totalNuts = array_sum(array_column($details, 'bunches_delivered'));
        $bruto     = $request->bruto !== null ? (float) $request->bruto : null;
        $tarra     = $request->tarra !== null ? (float) $request->tarra : null;

        return [
            'estate_code'           => $this->estateCode(),
            'division_code'         => trim($request->division_code),
            'delivery_note'         => $request->delivery_note ?: null,
            'receiving_point_code'  => $request->receiving_point_code ?: null,
            'license_number'        => $request->license_number ?: null,
            'seal_code'             => $request->seal_code ?: null,
            'kerani_kirim_emp_code' => $request->kerani_kirim_emp_code ?: null,
            'kerani_kirim_emp_name' => $kerani?->employee_name,
            'vendor_code'           => $request->vendor_code ?: null,
            'vendor_name'           => $vendor?->vendor_name,
            'transporter'           => $request->transporter ?: null,
            'bruto'                 => $bruto,
            'tarra'                 => $tarra,
            'actual_tonnage'        => ($bruto !== null && $tarra !== null) ? round($bruto - $tarra, 3) : null,
            'total_bunches'         => $totalNuts,
            'total_oph'             => count($details),
            'total_loose_fruit'     => 0,
            'remark'                => $request->remark ?: null,
            'is_deleted'            => false,
        ];
    }

    /** Build detail rows from posted chit ids, backfilling from the chit master. */
    protected function cleanDetails(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('details', []) as $d) {
            if (! is_array($d)) continue;
            $chitId = trim((string) ($d['oph_id'] ?? ''));
            if ($chitId === '') continue;

            $chit = CoconutOph::query()->actual()->whereKey($chitId)->first();
            if (! $chit) continue;

            $out[] = [
                'oph_id'                => $chitId,
                'oph_block_code'        => $chit->block_code ?: '0',
                'oph_tph_code'          => $chit->tph_code ?: '0',
                'oph_card_id'           => $chit->oph_card_id ?: null,
                'oph_platform_no'       => null,
                'bunches_delivered'     => (int) ($chit->nuts_total ?? 0),
                'loose_fruit_delivered' => 0,
            ];
        }
        return $out;
    }

    protected function cleanLoaders(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('loaders', []) as $l) {
            if (! is_array($l)) continue;
            $code = trim((string) ($l['employee_code'] ?? ''));
            if ($code === '') continue;
            $out[] = [
                'employee_code' => $code,
                'percentage'    => (float) ($l['percentage'] ?? 0),
            ];
        }
        return $out;
    }

    protected function saveDetails(string $cpId, array $details): void
    {
        foreach ($details as $d) {
            CpDetail::create(array_merge($d, [
                'company_id'         => $this->companyId(),
                'cp_id'              => $cpId,
                'detail_type'        => self::DETAIL_TYPE,
                'integration_status' => -1,
            ]));
        }
    }

    protected function saveLoaders(string $cpId, array $loaders): void
    {
        foreach ($loaders as $l) {
            CpLoader::create([
                'company_id'         => $this->companyId(),
                'cp_id'              => $cpId,
                'employee_code'      => $l['employee_code'],
                'employee_name'      => Employee::where('employee_code', $l['employee_code'])->value('employee_name') ?? '',
                'percentage'         => $l['percentage'],
                'loader_type'        => self::CP_TYPE,
                'integration_status' => -1,
            ]);
        }
    }

    protected function generateId(): string
    {
        return 'CPC' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);
    }

    /** @return array{0:string,1:string} */
    protected function resolveRange(Request $request): array
    {
        $key    = $this->routePrefix() . '.range';
        $stored = session($key, []);
        $from = $request->query('from', $stored['from'] ?? Carbon::today()->subDays(7)->toDateString());
        $to   = $request->query('to',   $stored['to']   ?? Carbon::today()->toDateString());
        try {
            $from = Carbon::parse($from)->toDateString();
            $to   = Carbon::parse($to)->toDateString();
        } catch (\Throwable $e) {
            $from = Carbon::today()->subDays(7)->toDateString();
            $to   = Carbon::today()->toDateString();
        }
        if ($from > $to) [$from, $to] = [$to, $from];
        session([$key => ['from' => $from, 'to' => $to]]);
        return [$from, $to];
    }
}
