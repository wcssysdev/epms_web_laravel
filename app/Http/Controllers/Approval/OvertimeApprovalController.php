<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\Overtime;
use App\Services\AuditService;
use App\Traits\ScopesToAssistantDivisions;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Overtime approval — Assistant Manager (division-scoped).
 * Batch approve/reject of pending overtime records for a chosen date.
 */
class OvertimeApprovalController extends BaseController
{
    use ScopesToAssistantDivisions;

    public function index(Request $request): View
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('overtime_approval_date', Carbon::today()->toDateString()));
        session(['overtime_approval_date' => $date->toDateString()]);

        $divisions = $this->approverDivisions();
        $noAccess  = $this->hasNoApproverDivisions($divisions);

        $build = fn (string $scope) => $noAccess ? collect()
            : Overtime::forDate($date->toDateString())
                ->inDivisions($divisions)
                ->{$scope}()
                ->orderBy('division_code')->orderBy('employee_name')
                ->get();

        return view('approval.overtime.index', [
            'date'     => $date,
            'pending'  => $build('pending'),
            'approved' => $build('approved'),
            'rejected' => $build('rejected'),
            'noAccess' => $noAccess,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $request->validate([
            'approval_type' => 'required|in:approved,rejected',
            'overtime_ids'  => 'required|array|min:1',
            'overtime_ids.*'=> 'required|string',
            'date'          => 'required|date',
        ]);

        $ids  = array_values(array_unique($validated['overtime_ids']));
        $type = $validated['approval_type'];
        $divisions = $this->approverDivisions();

        if ($this->hasNoApproverDivisions($divisions)) {
            return back()->with('error', 'You do not have access to any division.');
        }

        // Only pending records within scope are approvable
        $target = Overtime::whereIn('id', $ids)
            ->forDate($validated['date'])
            ->inDivisions($divisions)
            ->pending()
            ->get();

        if ($target->count() !== count($ids)) {
            return back()->with('error', 'Some overtime records are no longer available for approval.');
        }

        $approved = $type === 'approved';

        DB::transaction(function () use ($target, $approved) {
            foreach ($target as $ot) {
                $ot->update([
                    'is_approved' => $approved,
                    'approved_by' => $approved ? (string) $this->userId() : null,
                    'approved_at' => now(),
                    'updated_by'  => $this->userName(),
                ]);
            }
        });

        AuditService::log(
            AuditService::TYPE_APPROVAL,
            $approved ? AuditService::ACTION_APPROVE : AuditService::ACTION_REJECT,
            ucfirst($type) . ' ' . count($ids) . ' overtime record(s)'
        );

        return redirect()->route('approval.overtime.index', ['date' => $validated['date']])
            ->with('success', 'Overtime ' . $type . ' successfully.');
    }
}
