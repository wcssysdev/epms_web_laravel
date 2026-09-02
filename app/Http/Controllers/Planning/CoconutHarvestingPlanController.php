<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\CoconutHarvestingPlan;
use App\Models\Master\Division;
use App\Models\Master\Block;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Coconut Harvesting Plan — Asst Manager (50) + Estate Manager (40).
 * Only available on coconut-enabled companies (system_is_coconut).
 * Same draft → publish → approve/reject flow as the palm HarvestingPlan.
 */
class CoconutHarvestingPlanController extends BaseController
{
    /** Guard: block access when the company is not coconut-enabled. */
    private function guardCoconut(): ?RedirectResponse
    {
        // Resolve config from the authenticated user at call time. The BaseController
        // constructor can run before the auth middleware resolves the user, so the
        // cached companyConfig may be null; fall back to the live user.
        $enabled = $this->companyConfig?->isSystemEnabled('coconut')
            ?? \Illuminate\Support\Facades\Auth::user()?->companyConfig?->isSystemEnabled('coconut')
            ?? false;

        if (! $enabled) {
            return redirect()->route('dashboard')
                ->with('error', 'Coconut harvesting is not enabled for your company.');
        }
        return null;
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($block = $this->guardCoconut()) return $block;

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('coconut_plan_date', Carbon::tomorrow()->toDateString()));
        session(['coconut_plan_date' => $date->toDateString()]);

        $plans = CoconutHarvestingPlan::forDate($date->toDateString())
            ->orderBy('division_code')->orderBy('block_code')
            ->get();

        return view('planning.coconut_harvesting_plan.index', [
            'date'       => $date,
            'plans'      => $plans,
            'canApprove' => $this->roleLevel() <= 40,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($block = $this->guardCoconut()) return $block;

        return view('planning.coconut_harvesting_plan.form', [
            'plan'      => null,
            'divisions' => $this->divisions(),
            'date'      => session('coconut_plan_date', Carbon::tomorrow()->toDateString()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($block = $this->guardCoconut()) return $block;
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $this->validatePlan($request);

        CoconutHarvestingPlan::create(array_merge($validated, [
            'company_id'  => $this->companyId(),
            'estate_code' => $this->estateCode(),
            'is_approved' => $request->input('action') === 'publish'
                                ? CoconutHarvestingPlan::STATUS_PUBLISHED
                                : CoconutHarvestingPlan::STATUS_DRAFT,
            'created_by'  => $this->userName(),
            'updated_by'  => $this->userName(),
        ]));

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Created coconut harvesting plan for {$validated['division_code']}/{$validated['block_code']}");

        return redirect()->route('planning.coconut_harvesting_plan.index')
            ->with('success', 'Coconut harvesting plan saved successfully.');
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($block = $this->guardCoconut()) return $block;

        $plan = CoconutHarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isEditable()) {
            return redirect()->route('planning.coconut_harvesting_plan.index')
                ->with('error', 'Only draft or rejected plans can be edited.');
        }

        return view('planning.coconut_harvesting_plan.form', [
            'plan'      => $plan,
            'divisions' => $this->divisions(),
            'date'      => $plan->plan_date->toDateString(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($block = $this->guardCoconut()) return $block;
        if ($lock = $this->guardSystemLock()) return $lock;

        $plan = CoconutHarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isEditable()) {
            return redirect()->route('planning.coconut_harvesting_plan.index')
                ->with('error', 'Only draft or rejected plans can be edited.');
        }

        $validated = $this->validatePlan($request);

        $plan->update(array_merge($validated, [
            'is_approved' => $request->input('action') === 'publish'
                                ? CoconutHarvestingPlan::STATUS_PUBLISHED
                                : CoconutHarvestingPlan::STATUS_DRAFT,
            'updated_by'  => $this->userName(),
        ]));

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
            "Updated coconut harvesting plan #{$id}");

        return redirect()->route('planning.coconut_harvesting_plan.index')
            ->with('success', 'Coconut harvesting plan updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($block = $this->guardCoconut()) return $block;
        if ($lock = $this->guardSystemLock()) return $lock;

        $plan = CoconutHarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isEditable()) {
            return redirect()->route('planning.coconut_harvesting_plan.index')
                ->with('error', 'Only draft or rejected plans can be deleted.');
        }

        $plan->delete();

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
            "Deleted coconut harvesting plan #{$id}");

        return redirect()->route('planning.coconut_harvesting_plan.index')
            ->with('success', 'Coconut harvesting plan deleted.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        if ($block = $this->guardCoconut()) return $block;
        if ($lock = $this->guardSystemLock()) return $lock;

        if ($this->roleLevel() > 40) {
            return redirect()->route('planning.coconut_harvesting_plan.index')
                ->with('error', 'You are not authorised to approve coconut harvesting plans.');
        }

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'remark'   => 'nullable|string|max:255',
        ]);

        $plan = CoconutHarvestingPlan::find($id);
        abort_unless($plan, 404);

        if (! $plan->isPublished()) {
            return redirect()->route('planning.coconut_harvesting_plan.index')
                ->with('error', 'Only published plans can be approved or rejected.');
        }

        if ($validated['decision'] === 'rejected' && empty(trim((string) $validated['remark']))) {
            return redirect()->route('planning.coconut_harvesting_plan.index')
                ->with('error', 'Please provide a note when rejecting a plan.');
        }

        $status = $validated['decision'] === 'approved'
            ? CoconutHarvestingPlan::STATUS_APPROVED
            : CoconutHarvestingPlan::STATUS_REJECTED;

        $update = [
            'is_approved'     => $status,
            'approval_remark' => $validated['remark'] ?? null,
            'updated_by'      => $this->userName(),
        ];
        if ($status === CoconutHarvestingPlan::STATUS_APPROVED) {
            $update['approved_by']      = (string) $this->userId();
            $update['approved_by_name'] = $this->userName();
            $update['approved_at']      = now();
        }

        $plan->update($update);

        AuditService::log(
            AuditService::TYPE_APPROVAL,
            $status === CoconutHarvestingPlan::STATUS_APPROVED ? AuditService::ACTION_APPROVE : AuditService::ACTION_REJECT,
            ucfirst($validated['decision']) . " coconut harvesting plan #{$id}"
        );

        return redirect()->route('planning.coconut_harvesting_plan.index')
            ->with('success', 'Coconut harvesting plan ' . $validated['decision'] . '.');
    }

    /** AJAX: coconut blocks for a division. */
    public function getBlocks(Request $request): JsonResponse
    {
        $blocks = Block::byDivision($request->query('division_code'))
            ->byEstate($this->estateCode())
            ->byCropType('COCONUT')
            ->active()
            ->orderBy('block_code')
            ->get(['block_code', 'block_name', 'block_hectarage']);

        return $this->jsonSuccess('OK', $blocks->map(fn($b) => [
            'id'        => $b->block_code,
            'text'      => "{$b->block_code} - {$b->block_name}",
            'hectarage' => $b->block_hectarage,
        ]));
    }

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
