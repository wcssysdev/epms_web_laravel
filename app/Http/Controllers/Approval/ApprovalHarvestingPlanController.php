<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Approval Harvesting Plan (Assistant Manager role).
 * 
 * Handle both Palm (t_harvesting_plan) and Coconut (t_coconut_harvesting_plan).
 * Filter: is_approved = 0 (pending).
 * Action: approve (set is_approved=1) atau reject (set is_approved=-1).
 */
class ApprovalHarvestingPlanController extends BaseController
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'palm'); // palm or coconut
        [$date] = $this->resolveDate($request, "approval.harvesting_plan.{$type}.date", '+1 day');

        $table = $type === 'coconut' ? 't_coconut_harvesting_plan' : 't_harvesting_plan';
        $pending = $this->queryGroupedPending($date, $table);

        return view('approval.harvesting_plan.index', [
            'title' => 'Harvesting Plan Approval (' . ucfirst($type) . ')',
            'date' => $date,
            'type' => $type,
            'pending' => $pending,
        ]);
    }

    public function detail(Request $request)
    {
        $type = $request->query('type', 'palm');
        $date = $request->query('date', today()->addDay()->toDateString());
        $division = $request->query('division');
        $createdBy = $request->query('created_by');

        if (!$division || !$createdBy) {
            return redirect()->route('approval.harvesting_plan.index', ['type' => $type, 'date' => $date])
                ->with('error', 'Missing division or created_by parameter.');
        }

        $table = $type === 'coconut' ? 't_coconut_harvesting_plan' : 't_harvesting_plan';

        $plans = DB::table($table)
            ->where('plan_date', Carbon::parse($date)->toDateString())
            ->where('division_code', $division)
            ->where('created_by', $createdBy)
            ->where('is_approved', 0) // pending only
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('block_code')
            ->get();

        if ($plans->isEmpty()) {
            return redirect()->route('approval.harvesting_plan.index', ['type' => $type, 'date' => $date])
                ->with('error', 'No pending harvesting plans found.');
        }

        return view('approval.harvesting_plan.detail', [
            'title' => 'Harvesting Plan Approval Detail (' . ucfirst($type) . ')',
            'date' => $date,
            'type' => $type,
            'division' => $division,
            'createdBy' => $createdBy,
            'plans' => $plans,
        ]);
    }

    public function approve(Request $request)
    {
        $request->validate([
            'type' => 'required|in:palm,coconut',
            'date' => 'required|date',
            'division' => 'required|string',
            'created_by' => 'required|string',
            'action' => 'required|in:approve,reject',
            'remark' => 'nullable|string|max:500',
        ]);

        $type = $request->input('type');
        $date = Carbon::parse($request->input('date'))->toDateString();
        $division = $request->input('division');
        $createdBy = $request->input('created_by');
        $action = $request->input('action');
        $remark = $request->input('remark');

        $table = $type === 'coconut' ? 't_coconut_harvesting_plan' : 't_harvesting_plan';
        $newStatus = $action === 'approve' ? 1 : -1;
        $user = Auth::user();

        $updated = DB::table($table)
            ->where('plan_date', $date)
            ->where('division_code', $division)
            ->where('created_by', $createdBy)
            ->where('is_approved', 0) // hanya pending
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->update([
                'is_approved' => $newStatus,
                'approved_by' => $user->user_id ?? null,
                'approved_by_name' => $user->user_name ?? null,
                'approved_at' => now(),
                'approval_remark' => $remark,
            ]);

        if ($updated === 0) {
            return redirect()->route('approval.harvesting_plan.index', ['type' => $type, 'date' => $date])
                ->with('error', 'No pending harvesting plans found to ' . $action . '.');
        }

        $msg = $action === 'approve' ? 'approved' : 'rejected';
        return redirect()->route('approval.harvesting_plan.index', ['type' => $type, 'date' => $date])
            ->with('success', "$updated harvesting plan(s) $msg successfully.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function queryGroupedPending(string $date, string $table): array
    {
        return DB::table($table)
            ->selectRaw('COUNT(*) as block_count, SUM(qty_target) as total_qty, division_code, plan_date, assistant_emp_code, assistant_emp_name, created_by')
            ->where('plan_date', Carbon::parse($date)->toDateString())
            ->where('is_approved', 0)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->groupBy('division_code', 'plan_date', 'assistant_emp_code', 'assistant_emp_name', 'created_by')
            ->orderBy('division_code')
            ->get()
            ->toArray();
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
