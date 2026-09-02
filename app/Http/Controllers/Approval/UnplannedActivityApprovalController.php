<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\Workdone;
use App\Services\AuditService;
use App\Traits\ScopesToAssistantDivisions;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Unplanned Activity approval — Assistant Manager (division-scoped).
 * Reviews workdone rows where is_planned = false (activities done without a workplan).
 */
class UnplannedActivityApprovalController extends BaseController
{
    use ScopesToAssistantDivisions;

    public function index(Request $request): View
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('unplanned_activity_date', Carbon::today()->toDateString()));
        session(['unplanned_activity_date' => $date->toDateString()]);

        $divisions = $this->approverDivisions();
        $noAccess  = $this->hasNoApproverDivisions($divisions);

        $build = fn (string $scope) => $noAccess ? collect()
            : Workdone::unplanned()
                ->forDate($date->toDateString())
                ->inDivisions($divisions)
                ->{$scope}()
                ->orderBy('division_code')->orderBy('activity_code')
                ->get();

        return view('approval.unplanned_activity.index', [
            'date'     => $date,
            'pending'  => $build('pending'),
            'approved' => $build('approved'),
            'rejected' => $build('rejected'),
            'noAccess' => $noAccess,
        ]);
    }

    public function detail(string $id): View|RedirectResponse
    {
        $divisions = $this->approverDivisions();

        $workdone = Workdone::unplanned()
            ->inDivisions($divisions)
            ->where('id', $id)
            ->first();

        if (! $workdone) {
            return redirect()->route('approval.unplanned_activity.index')
                ->with('error', 'Unplanned activity not found or outside your approval scope.');
        }

        return view('approval.unplanned_activity.detail', [
            'workdone'  => $workdone,
            'materials' => $workdone->materials()->orderBy('id')->get(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $request->validate([
            'approval_type'  => 'required|in:approved,rejected',
            'workdone_ids'   => 'required|array|min:1',
            'workdone_ids.*' => 'required|string',
            'date'           => 'required|date',
        ]);

        $ids  = array_values(array_unique($validated['workdone_ids']));
        $type = $validated['approval_type'];
        $divisions = $this->approverDivisions();

        if ($this->hasNoApproverDivisions($divisions)) {
            return back()->with('error', 'You do not have access to any division.');
        }

        $target = Workdone::unplanned()
            ->whereIn('id', $ids)
            ->forDate($validated['date'])
            ->inDivisions($divisions)
            ->pending()
            ->get();

        if ($target->count() !== count($ids)) {
            return back()->with('error', 'Some unplanned activities are no longer available for approval.');
        }

        $approved = $type === 'approved';

        DB::transaction(function () use ($target, $approved) {
            foreach ($target as $wd) {
                $wd->update([
                    'is_approved'      => $approved,
                    'approved_by'      => $approved ? (string) $this->userId() : null,
                    'approved_by_name' => $approved ? $this->userName() : null,
                    'approved_at'      => now(),
                    'updated_by'       => $this->userName(),
                ]);
            }
        });

        AuditService::log(
            AuditService::TYPE_APPROVAL,
            $approved ? AuditService::ACTION_APPROVE : AuditService::ACTION_REJECT,
            ucfirst($type) . ' ' . count($ids) . ' unplanned activity record(s)'
        );

        return redirect()->route('approval.unplanned_activity.index', ['date' => $validated['date']])
            ->with('success', 'Unplanned activity ' . $type . ' successfully.');
    }
}
