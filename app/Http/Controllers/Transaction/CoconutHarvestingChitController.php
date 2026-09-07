<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\CoconutOph;
use App\Models\Transaction\CoconutOphDetail;
use App\Models\Transaction\CoconutOphPerson;
use App\Models\Master\Employee;
use App\Models\Master\Tph;
use App\Models\Master\CoconutMaterial;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Harvesting Chit (Coconut) — t_coconut_oph master-detail entry (Estate Staff).
 *
 * Header (block/tph/gang/checker + nuts_total) + material detail lines
 * (customer nut qty per coconut material) + harvester persons.
 * Coconut-enabled companies only.
 */
class CoconutHarvestingChitController extends BaseController
{
    use \App\Http\Controllers\Transaction\Concerns\GuardsSapIntegration;

    protected function routePrefix(): string { return 'transactions.harvesting_chit_coconut'; }
    protected function viewPrefix(): string  { return 'transaction.harvesting_chit_coconut'; }
    protected function title(): string       { return 'Harvesting Chit (Coconut)'; }

    protected function datatableColumns(): array
    {
        return [
            'created_at'            => 'Date',
            'division_code'         => 'Division',
            'block_code'            => 'Block',
            'tph_code'              => 'TPH',
            'gang_name'             => 'Gang',
            'checker_employee_name' => 'Checker',
            'nuts_total'            => 'Nuts',
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
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $query = CoconutOph::query()->actual()
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
            'persons'     => collect(),
        ], $this->formData()));
    }

    public function edit(string $id): View|RedirectResponse
    {
        $item = CoconutOph::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;
        if ($x = $this->guardChitInSentFdn($id)) return $x;
        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => $item,
            'details'     => $item->details()->get(),
            'persons'     => $item->persons()->get(),
        ], $this->formData()));
    }

    // ── STORE / UPDATE ─────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->validateChit($request);

        $id = $this->generateId();
        DB::transaction(function () use ($id, $request) {
            CoconutOph::create(array_merge($this->mapHeader($request), [
                'id'         => $id,
                'company_id' => $this->companyId(),
                'created_by' => $this->userName(),
                'updated_by' => $this->userName(),
            ]));
            $this->saveDetails($id, $request);
            $this->savePersons($id, $request);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE, "Created {$this->title()} {$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' saved successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $item = CoconutOph::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;
        if ($x = $this->guardChitInSentFdn($id)) return $x;

        $this->validateChit($request);

        DB::transaction(function () use ($item, $request) {
            $item->update(array_merge($this->mapHeader($request), ['updated_by' => $this->userName()]));
            CoconutOphDetail::where('coconut_oph_id', $item->id)->delete();
            CoconutOphPerson::where('coconut_oph_id', $item->id)->delete();
            $this->saveDetails($item->id, $request);
            $this->savePersons($item->id, $request);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE, "Updated {$this->title()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $item = CoconutOph::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapDelete($item)) return $sap;
        if ($x = $this->guardChitReferenced($id)) return $x;

        DB::transaction(function () use ($item) {
            CoconutOphDetail::where('coconut_oph_id', $item->id)->delete();
            CoconutOphPerson::where('coconut_oph_id', $item->id)->delete();
            $item->delete();
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE, "Deleted {$this->title()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')->with('success', $this->title() . ' deleted.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════

    protected function formData(): array
    {
        return [
            'divisions' => \App\Models\Master\Division::byEstate($this->estateCode())
                            ->orderBy('division_code')->get(['division_code', 'division_name']),
            'blocks'    => \App\Models\Master\Block::where('estate_code', $this->estateCode())
                            ->orderBy('block_code')->get(['division_code', 'block_code', 'block_name']),
            'tphs'      => Tph::where('estate_code', $this->estateCode())
                            ->orderBy('tph_code')->get(['division_code', 'block_code', 'tph_code']),
            'employees' => Employee::byEstate($this->estateCode())
                            ->orderBy('employee_code')->get(['employee_code', 'employee_name']),
            'materials' => CoconutMaterial::orderBy('material_code')->get(['material_code', 'material_desc']),
        ];
    }

    protected function validateChit(Request $request): void
    {
        $request->validate([
            'division_code'         => 'required|string|max:50',
            'block_code'            => 'required|string|max:50',
            'tph_code'              => 'required|string|max:50',
            'checker_employee_code' => 'required|string|max:100',
            'oph_card_id'           => 'nullable|string|max:100',
            'gang_code'             => 'nullable|string|max:100',
            'nuts_total'            => 'nullable|integer|min:0',
            'notes'                 => 'nullable|string|max:255',
        ]);
    }

    protected function mapHeader(Request $request): array
    {
        $checker = Employee::where('employee_code', $request->checker_employee_code)->first();

        return [
            'plant_code'            => $this->plantCode() ?: '',
            'estate_code'           => $this->estateCode(),
            'division_code'         => trim($request->division_code),
            'block_code'            => trim($request->block_code),
            'tph_code'              => trim($request->tph_code),
            'oph_card_id'           => $request->oph_card_id ?: null,
            'gang_code'             => $request->gang_code ?: null,
            'gang_name'             => $request->gang_name ?: null,
            'checker_employee_code' => trim($request->checker_employee_code),
            'checker_employee_name' => $checker?->employee_name ?? '',
            'nuts_total'            => (int) ($request->nuts_total ?? 0),
            'notes'                 => $request->notes ?: null,
            'is_planned'            => false,
            'is_approved'           => false,
            'is_closed'             => false,
            'closing_is_approved'   => false,
            'is_deleted'            => false,
            'adjustment_status'     => 0,
            'integration_status'    => -1,
        ];
    }

    protected function saveDetails(string $ophId, Request $request): void
    {
        foreach ((array) $request->input('details', []) as $d) {
            if (! is_array($d)) continue;
            $code = trim((string) ($d['material_code'] ?? ''));
            if ($code === '') continue;
            CoconutOphDetail::create([
                'company_id'         => $this->companyId(),
                'coconut_oph_id'     => $ophId,
                'material_code'      => $code,
                'material_name'      => CoconutMaterial::where('material_code', $code)->value('material_desc') ?? '',
                'customer_nut_qty'   => (float) ($d['customer_nut_qty'] ?? 0),
                'is_locked'          => false,
                'closing_is_approved'=> false,
                'is_deleted'         => false,
                'adjustment_status'  => 0,
                'integration_status' => -1,
            ]);
        }
    }

    protected function savePersons(string $ophId, Request $request): void
    {
        foreach ((array) $request->input('persons', []) as $p) {
            if (! is_array($p)) continue;
            $code = trim((string) ($p['employee_code'] ?? ''));
            if ($code === '') continue;
            CoconutOphPerson::create([
                'company_id'         => $this->companyId(),
                'coconut_oph_id'     => $ophId,
                'employee_code'      => $code,
                'employee_name'      => Employee::where('employee_code', $code)->value('employee_name') ?? '',
                'activity_type'      => $p['activity_type'] ?? null,
                'integration_status' => -1,
            ]);
        }
    }

    protected function generateId(): string
    {
        return 'CHT' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);
    }

    // ── Cross-table SAP guard (chit referenced by coconut FDN) ─────────────────

    /** Block edit if the chit is part of a coconut FDN already sent to SAP (status 2). */
    protected function guardChitInSentFdn(string $chitId): ?RedirectResponse
    {
        $inSent = DB::table('t_coconut_fdn_detail as d')
            ->join('t_coconut_fdn as h', 'h.id', '=', 'd.coconut_fdn_id')
            ->where('d.coconut_oph_id', $chitId)
            ->where('h.integration_status', 2)
            ->exists();

        if ($inSent) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', 'This Harvesting Chit is part of a coconut FDN already sent to SAP and can no longer be edited.');
        }
        return null;
    }

    /** Block delete if the chit is referenced by ANY coconut FDN detail. */
    protected function guardChitReferenced(string $chitId): ?RedirectResponse
    {
        $referenced = DB::table('t_coconut_fdn_detail')->where('coconut_oph_id', $chitId)->exists();
        if ($referenced) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', 'This Harvesting Chit is used in a coconut FDN and cannot be deleted. Remove it from the FDN first.');
        }
        return null;
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
