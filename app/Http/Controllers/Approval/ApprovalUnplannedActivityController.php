<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Approval Unplanned Activity (Assistant Manager role).
 * 
 * Unplanned Activity = t_workdone WHERE is_planned=0 (dari mobile).
 * Filter: is_approved = 0 (pending).
 * Action: approve (set is_approved=1) atau reject (set is_approved=-1).
 */
class ApprovalUnplannedActivityController extends BaseController
{
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request, 'approval.unplanned_activity.date', '0 day');

        $pending = $this->queryPending($date);

        return view('approval.unplanned_activity.index', [
            'title' => 'Unplanned Activity Approval',
            'date' => $date,
            'pending' => $pending,
        ]);
    }

    public function approve(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:approve,reject',
            'remark' => 'nullable|string|max:500',
        ]);

        $ids = $request->input('ids');
        $action = $request->input('action');
        $remark = $request->input('remark');
        $newStatus = $action === 'approve' ? 1 : -1;
        $user = Auth::user();

        $updated = DB::table('t_workdone')
            ->whereIn('id', $ids)
            ->where('is_planned', 0) // unplanned only
            ->where('is_approved', 0) // pending only
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->update([
                'is_approved' => $newStatus,
                'approved_by' => $user->user_id ?? null,
                'approved_by_name' => $user->user_name ?? null,
                'approved_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->with('error', 'No pending unplanned activities found to ' . $action . '.');
        }

        $msg = $action === 'approve' ? 'approved' : 'rejected';
        return back()->with('success', "$updated unplanned activity/activities $msg successfully.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function queryPending(string $date): array
    {
        return DB::table('t_workdone')
            ->select('id', 'workdone_date', 'division_code', 'block_code', 'activity_code', 'activity_name', 
                     'employee_code', 'employee_name', 'qty', 'created_by', 'created_at')
            ->where('workdone_date', Carbon::parse($date)->toDateString())
            ->where('is_planned', 0)
            ->where('is_approved', 0)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('division_code')
            ->orderBy('block_code')
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
