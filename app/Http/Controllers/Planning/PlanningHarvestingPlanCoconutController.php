<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Planning Harvesting Plan Coconut (Estate Manager role).
 * 
 * Harvesting Plan Coconut = rencana panen kelapa harian per division/block.
 * Status approval: NULL=draft, 0=pending (submitted), 1=approved, -1=rejected.
 * Assistant Manager melakukan approval via Approval\ApprovalHarvestingPlanController.
 */
class PlanningHarvestingPlanCoconutController extends BaseController
{
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request, 'planning.harvesting_plan_coconut.date', '+1 day');

        // Group by division + date
        $drafted = $this->queryGroupedByDivision($date, null);
        $submitted = $this->queryGroupedByDivision($date, 0);
        $approved = $this->queryGroupedByDivision($date, 1);
        $rejected = $this->queryGroupedByDivision($date, -1);

        return view('planning.harvesting_plan_coconut.index', [
            'title' => 'Harvesting Plan (Coconut)',
            'date' => $date,
            'drafted' => $drafted,
            'submitted' => $submitted,
            'approved' => $approved,
            'rejected' => $rejected,
        ]);
    }

    public function detail(Request $request)
    {
        $date = $request->query('date', today()->addDay()->toDateString());
        $division = $request->query('division');
        $createdBy = $request->query('created_by');

        if (!$division || !$createdBy) {
            return redirect()->route('planning.harvesting_plan_coconut.index', ['date' => $date])
                ->with('error', 'Missing division or created_by parameter.');
        }

        $plans = DB::table('t_coconut_harvesting_plan')
            ->where('plan_date', Carbon::parse($date)->toDateString())
            ->where('division_code', $division)
            ->where('created_by', $createdBy)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('block_code')
            ->get();

        if ($plans->isEmpty()) {
            return redirect()->route('planning.harvesting_plan_coconut.index', ['date' => $date])
                ->with('error', 'No coconut harvesting plans found for selected criteria.');
        }

        return view('planning.harvesting_plan_coconut.detail', [
            'title' => 'Harvesting Plan Coconut Detail',
            'date' => $date,
            'division' => $division,
            'plans' => $plans,
        ]);
    }

    public function submitForApproval(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'division' => 'required|string',
            'created_by' => 'required|string',
        ]);

        $date = Carbon::parse($request->input('date'))->toDateString();
        $division = $request->input('division');
        $createdBy = $request->input('created_by');

        $updated = DB::table('t_coconut_harvesting_plan')
            ->where('plan_date', $date)
            ->where('division_code', $division)
            ->where('created_by', $createdBy)
            ->whereNull('is_approved')
            ->update(['is_approved' => 0]);

        if ($updated === 0) {
            return redirect()->route('planning.harvesting_plan_coconut.index', ['date' => $date])
                ->with('error', 'No draft coconut harvesting plans found to submit.');
        }

        return redirect()->route('planning.harvesting_plan_coconut.index', ['date' => $date])
            ->with('success', "$updated coconut harvesting plan(s) submitted for approval.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function queryGroupedByDivision(string $date, ?int $approvalStatus): array
    {
        $q = DB::table('t_coconut_harvesting_plan')
            ->selectRaw('COUNT(*) as block_count, SUM(qty_target) as total_qty, division_code, plan_date, assistant_emp_code, assistant_emp_name, created_by')
            ->where('plan_date', Carbon::parse($date)->toDateString())
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->groupBy('division_code', 'plan_date', 'assistant_emp_code', 'assistant_emp_name', 'created_by')
            ->orderBy('division_code');

        if ($approvalStatus === null) {
            $q->whereNull('is_approved');
        } else {
            $q->where('is_approved', $approvalStatus);
        }

        return $q->get()->toArray();
    }

    private function resolveDate(Request $request, string $sessionKey, string $defaultOffset = '0 day'): array
    {
        $date = $request->query('date', session($sessionKey, Carbon::today()->modify($defaultOffset)->toDateString()));
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            $date = Carbon::today()->modify($defaultOffset)->toDateString();
        }
        session([$sessionKey => $date]);
        return [$date];
    }
}
