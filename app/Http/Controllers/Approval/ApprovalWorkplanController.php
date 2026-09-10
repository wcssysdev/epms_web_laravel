<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Approval Workplan (Assistant Manager role).
 * 
 * Assistant Manager meng-approve atau reject workplan yang sudah disubmit Estate Manager.
 * Filter: is_approved = 0 (pending).
 * Action: approve (set is_approved=1) atau reject (set is_approved=-1).
 */
class ApprovalWorkplanController extends BaseController
{
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request, 'approval.workplan.date', '+1 day');

        // Pending approval only (is_approved = 0)
        $pending = $this->queryGroupedPending($date);

        return view('approval.workplan.index', [
            'title' => 'Workplan Approval',
            'date' => $date,
            'pending' => $pending,
        ]);
    }

    public function detail(Request $request)
    {
        $date = $request->query('date', today()->addDay()->toDateString());
        $division = $request->query('division');
        $createdBy = $request->query('created_by');

        if (!$division || !$createdBy) {
            return redirect()->route('approval.workplan.index', ['date' => $date])
                ->with('error', 'Missing division or created_by parameter.');
        }

        $workplans = DB::table('t_workplan')
            ->where('workplan_date', Carbon::parse($date)->toDateString())
            ->where('division_code', $division)
            ->where('created_by', $createdBy)
            ->where('is_approved', 0) // pending only
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('block_code')
            ->get();

        if ($workplans->isEmpty()) {
            return redirect()->route('approval.workplan.index', ['date' => $date])
                ->with('error', 'No pending workplans found.');
        }

        return view('approval.workplan.detail', [
            'title' => 'Workplan Approval Detail',
            'date' => $date,
            'division' => $division,
            'createdBy' => $createdBy,
            'workplans' => $workplans,
        ]);
    }

    public function approve(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'division' => 'required|string',
            'created_by' => 'required|string',
            'action' => 'required|in:approve,reject',
            'remark' => 'nullable|string|max:500',
        ]);

        $date = Carbon::parse($request->input('date'))->toDateString();
        $division = $request->input('division');
        $createdBy = $request->input('created_by');
        $action = $request->input('action');
        $remark = $request->input('remark');

        $newStatus = $action === 'approve' ? 1 : -1;
        $user = Auth::user();

        $updated = DB::table('t_workplan')
            ->where('workplan_date', $date)
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
            return redirect()->route('approval.workplan.index', ['date' => $date])
                ->with('error', 'No pending workplans found to ' . $action . '.');
        }

        $msg = $action === 'approve' ? 'approved' : 'rejected';
        return redirect()->route('approval.workplan.index', ['date' => $date])
            ->with('success', "$updated workplan(s) $msg successfully.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function queryGroupedPending(string $date): array
    {
        return DB::table('t_workplan')
            ->selectRaw('COUNT(*) as activity_count, division_code, workplan_date, created_by')
            ->where('workplan_date', Carbon::parse($date)->toDateString())
            ->where('is_approved', 0)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->groupBy('division_code', 'workplan_date', 'created_by')
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
