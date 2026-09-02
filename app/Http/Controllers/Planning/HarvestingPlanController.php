<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\HarvestingPlan;
use App\Models\Master\Division;
use App\Models\Master\Block;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Harvesting plan (palm) module.
 *
 * Asst Manager (50) / Estate Manager (40) create plans per division/block/date.
 * Estate Manager approves. Flow mirrors Workplan (draft → published → approved/rejected).
 */
class HarvestingPlanController extends BaseController
{
    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('harvesting_plan_date', Carbon::tomorrow()->toDateString()));
        session(['harvesting_plan_date' => $date->toDateString()]);

        $plans = HarvestingPlan::forDate($date->toDateString())
            ->orderBy('division_code')
            ->orderBy('block_code')
            ->get();

        $canApprove = $this->roleLevel() <= 40; // Estate Manager and above

        return view('planning.harvesting_plan.index', [
            'date'       => $date,
            'plans'      => $plans,
            'canApprove' => $canApprove,
        ]);
    }

    // ── CREATE ────────────────────────────────────────────────────────────────
    public function create(): View
    {
        return view('planning.harvesting_plan.form', [
            'plan'      => null,
            'divisions' => $this->divisions(),
            'date'      => session('harvesting_plan_date', Carbon::tomorrow()->toDateString()),
        ]);
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $this->validatePlan($request);

        HarvestingPlan::create(array_merge($validated, [
            'company_id'  => $this->companyId(),
            'estate_code' => $this->estateCode(),
            'is_approved' => $request->input('action') === 'publish'
                                ? HarvestingPlan::STATUS_PUBLISHED
                                : HarvestingPlan::STATUS_DRAFT,
            'created_by'  => $this->userName(),
            'updated_by'  => $this->userName(),
        ]));

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Created harvesting plan for {$validated['division_code']}/{$validated['block_code']}");

        return redirect()->route('planning.harvesting_plan.index')
            ->with('success', 'Harvesting plan saved successfully.');
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    public function edit(int $id): View|RedirectResponse
    {
        $plan = HarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isEditable()) {
            return redirect()->route('planning.harvesting_plan.index')
                ->with('error', 'Only draft or rejected plans can be edited.');
        }

        return view('planning.harvesting_plan.form', [
            'plan'      => $plan,
            'divisions' => $this->divisions(),
            'date'      => $plan->plan_date->toDateString(),
        ]);
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $plan = HarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isEditable()) {
            return redirect()->route('planning.harvesting_plan.index')
                ->with('error', 'Only draft or rejected plans can be edited.');
        }

        $validated = $this->validatePlan($request);

        $plan->update(array_merge($validated, [
            'is_approved' => $request->input('action') === 'publish'
                                ? HarvestingPlan::STATUS_PUBLISHED
                                : HarvestingPlan::STATUS_DRAFT,
            'updated_by'  => $this->userName(),
        ]));

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
            "Updated harvesting plan #{$id}");

        return redirect()->route('planning.harvesting_plan.index')
            ->with('success', 'Harvesting plan updated successfully.');
    }

    // ── DESTROY ─────────────────────────────────────────────────────────────
    public function destroy(int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $plan = HarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isEditable()) {
            return redirect()->route('planning.harvesting_plan.index')
                ->with('error', 'Only draft or rejected plans can be deleted.');
        }

        $plan->delete();

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
            "Deleted harvesting plan #{$id}");

        return redirect()->route('planning.harvesting_plan.index')
            ->with('success', 'Harvesting plan deleted.');
    }

    // ── APPROVE / REJECT (Estate Manager, level 40) ───────────────────────────
    public function approve(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        // Only Estate Manager and above may approve
        if ($this->roleLevel() > 40) {
            return redirect()->route('planning.harvesting_plan.index')
                ->with('error', 'You are not authorised to approve harvesting plans.');
        }

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'remark'   => 'nullable|string|max:255',
        ]);

        $plan = HarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isPublished()) {
            return redirect()->route('planning.harvesting_plan.index')
                ->with('error', 'Only published plans can be approved or rejected.');
        }

        if ($validated['decision'] === 'rejected' && empty(trim((string) $validated['remark']))) {
            return redirect()->route('planning.harvesting_plan.index')
                ->with('error', 'Please provide a note when rejecting a plan.');
        }

        $status = $validated['decision'] === 'approved'
            ? HarvestingPlan::STATUS_APPROVED
            : HarvestingPlan::STATUS_REJECTED;

        $update = [
            'is_approved'     => $status,
            'approval_remark' => $validated['remark'] ?? null,
            'updated_by'      => $this->userName(),
        ];
        if ($status === HarvestingPlan::STATUS_APPROVED) {
            $update['approved_by']      = (string) $this->userId();
            $update['approved_by_name'] = $this->userName();
            $update['approved_at']      = now();
        }

        $plan->update($update);

        AuditService::log(
            AuditService::TYPE_APPROVAL,
            $status === HarvestingPlan::STATUS_APPROVED ? AuditService::ACTION_APPROVE : AuditService::ACTION_REJECT,
            ucfirst($validated['decision']) . " harvesting plan #{$id}"
        );

        return redirect()->route('planning.harvesting_plan.index')
            ->with('success', 'Harvesting plan ' . $validated['decision'] . '.');
    }

    // ── AJAX: blocks by division ──────────────────────────────────────────────
    public function getBlocks(Request $request): JsonResponse
    {
        $blocks = Block::byDivision($request->query('division_code'))
            ->byEstate($this->estateCode())
            ->active()
            ->orderBy('block_code')
            ->get(['block_code', 'block_name', 'block_hectarage']);

        return $this->jsonSuccess('OK', $blocks->map(fn($b) => [
            'id'        => $b->block_code,
            'text'      => "{$b->block_code} - {$b->block_name}",
            'hectarage' => $b->block_hectarage,
        ]));
    }

    // ── HELPERS ─────────────────────────────────────────────────────────────
    private function validatePlan(Request $request): array
    {
        return $request->validate([
            'plan_date'          => 'required|date',
            'division_code'      => 'required|string|max:50',
            'block_code'         => 'required|string|max:50',
            'total_hk'           => 'required|integer|min:0',
            'qty_target'         => 'required|integer|min:0',
            'ha'                 => 'nullable|string|max:50',
            'assistant_emp_code' => 'nullable|string|max:100',
            'assistant_emp_name' => 'nullable|string|max:150',
        ]);
    }

    private function divisions()
    {
        return Division::byEstate($this->estateCode())
            ->orderBy('division_code')
            ->get(['division_code', 'division_name']);
    }
}
