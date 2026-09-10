<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Approval OPH (Assistant Manager role).
 * 
 * Filter: is_approved = 0 (pending).
 * Action: approve (set is_approved=1) atau reject (set is_approved=-1).
 */
class ApprovalOphController extends BaseController
{
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request, 'approval.oph.date', '0 day');

        $pending = $this->queryPending($date);

        return view('approval.oph.index', [
            'title' => 'OPH Approval',
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

        $updated = DB::table('t_oph')
            ->whereIn('id', $ids)
            ->where('is_planned', 0) // unplanned only (from mobile)
            ->where('is_approved', 0) // pending only
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->update([
                'is_approved' => $newStatus,
                'approved_by' => $user->user_id ?? null,
                'approved_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->with('error', 'No pending OPH records found to ' . $action . '.');
        }

        $msg = $action === 'approve' ? 'approved' : 'rejected';
        return back()->with('success', "$updated OPH record(s) $msg successfully.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function queryPending(string $date): array
    {
        return DB::table('t_oph')
            ->selectRaw("id, division_code, block_code, mandor_employee_code, mandor_employee_name, 
                         bunches_total, tph_code, DATE(created_at) as oph_date, created_at")
            ->whereRaw("DATE(created_at) = ?", [Carbon::parse($date)->toDateString()])
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
