<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\GiPlan;
use App\Models\Transaction\GiPlanDetail;
use App\Models\Master\Division;
use App\Models\Master\Material;
use App\Models\Master\CostCenter;
use App\Models\Global\MovementType;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Goods Issue Plan — Admin (30) / Estate Manager (40) / Assistant Manager (50).
 *
 * Header (tr_gi_plan) + material detail lines (tr_gi_plan_detail).
 * Simplified Laravel schema — no SAP maintenance-order / VRA / WBS integration.
 * Flow: draft → published → approved/rejected (tri-state smallint).
 */
class GiPlanController extends BaseController
{
    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('gi_plan_date', Carbon::today()->toDateString()));
        session(['gi_plan_date' => $date->toDateString()]);

        $base = GiPlan::forDate($date->toDateString())->orderByDesc('created_at');

        return view('transaction.gi_plan.index', [
            'date'      => $date,
            'drafts'    => (clone $base)->draft()->get(),
            'published' => (clone $base)->published()->get(),
            'approved'  => (clone $base)->approved()->get(),
            'rejected'  => (clone $base)->rejected()->get(),
            'canApprove'=> $this->roleLevel() <= 40,
        ]);
    }

    // ── CREATE ──────────────────────────────────────────────────────────────
    public function create(): View
    {
        return view('transaction.gi_plan.form', [
            'giPlan'        => null,
            'divisions'     => $this->divisions(),
            'movementTypes' => $this->movementTypes(),
            'costCenters'   => $this->costCenters(),
            'details'       => [],
            'detailsJson'   => [],
            'date'          => session('gi_plan_date', Carbon::today()->toDateString()),
        ]);
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $this->validateGiPlan($request);
        $details   = $this->cleanDetails($request->input('details', []));

        if ($details === []) {
            return back()->withInput()->with('error', 'Add at least one material line.');
        }
        foreach ($details as $i => $d) {
            $request->validate([
                "details.$i.material_code" => 'required|string|max:100',
                "details.$i.qty"           => 'required|numeric|min:0',
            ]);
        }

        $id = $this->generateGiPlanId();

        DB::transaction(function () use ($id, $validated, $details, $request) {
            GiPlan::create([
                'id'            => $id,
                'company_id'    => $this->companyId(),
                'plan_date'     => $validated['plan_date'],
                'estate_code'   => $this->estateCode(),
                'division_code' => $validated['division_code'] ?? null,
                'plant_code'    => $this->plantCode(),
                'sloc_code'     => $validated['sloc_code'] ?? null,
                'movement_type' => $validated['movement_type'],
                'is_approved'   => $request->input('action') === 'publish'
                                    ? GiPlan::STATUS_PUBLISHED
                                    : GiPlan::STATUS_DRAFT,
                'created_by'    => $this->userName(),
                'updated_by'    => $this->userName(),
            ]);

            $this->saveDetails($id, $details);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Created GI plan {$id}");

        return redirect()->route('transactions.gi_plan.index')
            ->with('success', 'GI plan saved successfully.');
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    public function edit(string $id): View|RedirectResponse
    {
        $giPlan = GiPlan::where('id', $id)->first();
        abort_unless($giPlan, 404);

        if (! $giPlan->isEditable()) {
            return redirect()->route('transactions.gi_plan.index')
                ->with('error', 'Only draft or rejected GI plans can be edited.');
        }

        $details = $giPlan->details()->orderBy('id')->get();

        return view('transaction.gi_plan.form', [
            'giPlan'        => $giPlan,
            'divisions'     => $this->divisions(),
            'movementTypes' => $this->movementTypes(),
            'costCenters'   => $this->costCenters(),
            'details'       => $details,
            'detailsJson'   => $details->map(fn ($d) => [
                'material_code' => $d->material_code ?? '',
                'material_name' => $d->material_name ?? '',
                'qty'           => $d->qty ?? '',
                'uom'           => $d->uom ?? '',
                'cost_center'   => $d->cost_center ?? '',
                'order_number'  => $d->order_number ?? '',
            ])->values(),
            'date'          => $giPlan->plan_date->toDateString(),
        ]);
    }

    // ── UPDATE ──────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $giPlan = GiPlan::where('id', $id)->first();
        abort_unless($giPlan, 404);

        if (! $giPlan->isEditable()) {
            return redirect()->route('transactions.gi_plan.index')
                ->with('error', 'Only draft or rejected GI plans can be edited.');
        }

        $validated = $this->validateGiPlan($request);
        $details   = $this->cleanDetails($request->input('details', []));

        if ($details === []) {
            return back()->withInput()->with('error', 'Add at least one material line.');
        }
        foreach ($details as $i => $d) {
            $request->validate([
                "details.$i.material_code" => 'required|string|max:100',
                "details.$i.qty"           => 'required|numeric|min:0',
            ]);
        }

        DB::transaction(function () use ($giPlan, $validated, $details, $request) {
            $giPlan->update([
                'plan_date'     => $validated['plan_date'],
                'division_code' => $validated['division_code'] ?? null,
                'sloc_code'     => $validated['sloc_code'] ?? null,
                'movement_type' => $validated['movement_type'],
                'is_approved'   => $request->input('action') === 'publish'
                                    ? GiPlan::STATUS_PUBLISHED
                                    : GiPlan::STATUS_DRAFT,
                'updated_by'    => $this->userName(),
            ]);

            GiPlanDetail::where('gi_plan_id', $giPlan->id)->delete();
            $this->saveDetails($giPlan->id, $details);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
            "Updated GI plan {$giPlan->id}");

        return redirect()->route('transactions.gi_plan.index')
            ->with('success', 'GI plan updated successfully.');
    }

    // ── DESTROY ─────────────────────────────────────────────────────────────
    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $giPlan = GiPlan::where('id', $id)->first();
        abort_unless($giPlan, 404);

        if (! $giPlan->isEditable()) {
            return redirect()->route('transactions.gi_plan.index')
                ->with('error', 'Only draft or rejected GI plans can be deleted.');
        }

        DB::transaction(function () use ($giPlan) {
            GiPlanDetail::where('gi_plan_id', $giPlan->id)->delete();
            $giPlan->delete();
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
            "Deleted GI plan {$id}");

        return redirect()->route('transactions.gi_plan.index')
            ->with('success', 'GI plan deleted.');
    }

    // ── SHOW (read-only detail) ───────────────────────────────────────────────
    public function show(string $id): View|RedirectResponse
    {
        $giPlan = GiPlan::where('id', $id)->first();
        abort_unless($giPlan, 404);

        return view('transaction.gi_plan.detail', [
            'giPlan'  => $giPlan,
            'details' => $giPlan->details()->orderBy('id')->get(),
        ]);
    }

    // ── PUBLISH ───────────────────────────────────────────────────────────────
    public function publish(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $giPlan = GiPlan::where('id', $id)->first();
        abort_unless($giPlan, 404);

        if (! $giPlan->isEditable()) {
            return redirect()->route('transactions.gi_plan.index')
                ->with('error', 'Only draft or rejected GI plans can be published.');
        }

        $giPlan->update([
            'is_approved' => GiPlan::STATUS_PUBLISHED,
            'updated_by'  => $this->userName(),
        ]);

        return redirect()->route('transactions.gi_plan.index')
            ->with('success', 'GI plan published for approval.');
    }

    // ── APPROVE / REJECT (Estate Manager, level 40) ───────────────────────────
    public function approve(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        if ($this->roleLevel() > 40) {
            return redirect()->route('transactions.gi_plan.index')
                ->with('error', 'You are not authorised to approve GI plans.');
        }

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'remark'   => 'nullable|string|max:255',
        ]);

        $giPlan = GiPlan::where('id', $id)->first();
        abort_unless($giPlan, 404);

        if (! $giPlan->isPublished()) {
            return redirect()->route('transactions.gi_plan.index')
                ->with('error', 'Only published GI plans can be approved or rejected.');
        }

        if ($validated['decision'] === 'rejected' && empty(trim((string) $validated['remark']))) {
            return redirect()->route('transactions.gi_plan.index')
                ->with('error', 'Please provide a note when rejecting a GI plan.');
        }

        $status = $validated['decision'] === 'approved'
            ? GiPlan::STATUS_APPROVED
            : GiPlan::STATUS_REJECTED;

        $update = [
            'is_approved'     => $status,
            'approval_remark' => $validated['remark'] ?? null,
            'updated_by'      => $this->userName(),
        ];
        if ($status === GiPlan::STATUS_APPROVED) {
            $update['approved_by']      = (string) $this->userId();
            $update['approved_by_name'] = $this->userName();
            $update['approved_at']      = now();
        }

        $giPlan->update($update);

        AuditService::log(
            AuditService::TYPE_APPROVAL,
            $status === GiPlan::STATUS_APPROVED ? AuditService::ACTION_APPROVE : AuditService::ACTION_REJECT,
            ucfirst($validated['decision']) . " GI plan {$id}"
        );

        return redirect()->route('transactions.gi_plan.index')
            ->with('success', 'GI plan ' . $validated['decision'] . '.');
    }

    // ── AJAX: material search ─────────────────────────────────────────────────
    public function searchMaterials(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('term', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = 25;

        $query = Material::where(fn($q) =>
            $q->where('material_code', 'ilike', "%{$term}%")
              ->orWhere('material_name', 'ilike', "%{$term}%")
        );

        $total = (clone $query)->count();
        $rows  = $query->orderBy('material_code')->forPage($page, $pageSize)
            ->get(['material_code', 'material_name', 'material_uom']);

        return response()->json([
            'results' => $rows->map(fn($m) => [
                'id'   => $m->material_code,
                'text' => "{$m->material_name} - {$m->material_code}",
                'uom'  => $m->material_uom,
            ]),
            'pagination' => ['more' => ($page * $pageSize) < $total],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ════════════════════════════════════════════════════════════════════════

    private function validateGiPlan(Request $request): array
    {
        return $request->validate([
            'plan_date'     => 'required|date',
            'division_code' => 'nullable|string|max:50',
            'sloc_code'     => 'nullable|string|max:50',
            'movement_type' => 'required|string|max:20',
        ]);
    }

    private function cleanDetails(array $raw): array
    {
        $clean = [];
        foreach ($raw as $d) {
            if (! is_array($d)) continue;
            $code = trim((string) ($d['material_code'] ?? ''));
            $qty  = trim((string) ($d['qty'] ?? ''));
            if ($code === '' && $qty === '') continue;
            $clean[] = [
                'material_code' => $code,
                'material_name' => trim((string) ($d['material_name'] ?? '')),
                'qty'           => $qty,
                'uom'           => trim((string) ($d['uom'] ?? '')),
                'cost_center'   => trim((string) ($d['cost_center'] ?? '')),
                'order_number'  => trim((string) ($d['order_number'] ?? '')),
            ];
        }
        return array_values($clean);
    }

    private function saveDetails(string $giPlanId, array $details): void
    {
        foreach ($details as $d) {
            $name = $d['material_name'] ?: (Material::where('material_code', $d['material_code'])->value('material_name') ?? '');
            GiPlanDetail::create([
                'company_id'    => $this->companyId(),
                'gi_plan_id'    => $giPlanId,
                'material_code' => $d['material_code'],
                'material_name' => $name,
                'qty'           => $d['qty'],
                'uom'           => $d['uom'] ?: null,
                'cost_center'   => $d['cost_center'] ?: null,
                'order_number'  => $d['order_number'] ?: null,
            ]);
        }
    }

    private function generateGiPlanId(): string
    {
        return 'GI' . $this->estateCode() . now()->format('YmdHis') . $this->userId();
    }

    private function divisions()
    {
        return Division::byEstate($this->estateCode())
            ->orderBy('division_code')
            ->get(['division_code', 'division_name']);
    }

    private function movementTypes()
    {
        return MovementType::orderBy('mvt_type_code')
            ->get(['mvt_type_code', 'mvt_type_desc']);
    }

    private function costCenters()
    {
        return CostCenter::orderBy('cc_code')
            ->get(['cc_code', 'cc_desc']);
    }
}
