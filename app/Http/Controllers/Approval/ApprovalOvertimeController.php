<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Approval Overtime (Assistant Manager role).
 * 
 * Filter: is_approved = 0 (pending).
 * Action: approve (set is_approved=1) atau reject (set is_approved=-1).
 */
class ApprovalOvertimeController extends BaseController
{
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request, 'approval.overtime.date', '0 day');

        $pending = $this->queryPending($date);

        return view('approval.overtime.index', [
            'title' => 'Overtime Approval',
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

        $updated = DB::table('t_overtime')
            ->whereIn('id', $ids)
            ->where('is_approved', 0) // pending only
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->update([
                'is_approved' => $newStatus,
                'approved_by' => $user->user_id ?? null,
                'approved_by_name' => $user->user_name ?? null,
                'approved_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->with('error', 'No pending overtime records found to ' . $action . '.');
        }

        $msg = $action === 'approve' ? 'approved' : 'rejected';
        return back()->with('success', "$updated overtime record(s) $msg successfully.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function queryPending(string $date): array
    {
        return DB::table('t_overtime')
            ->select('id', 'overtime_date', 'division_code', 'employee_code', 'employee_name',
                     'duration_hours', 'activity_code', 'activity_name', 'block_code', 'created_by', 'created_at')
            ->where('overtime_date', Carbon::parse($date)->toDateString())
            ->where('is_approved', 0)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('division_code')
            ->orderBy('employee_code')
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
