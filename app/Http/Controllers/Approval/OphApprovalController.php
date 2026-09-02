<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\Oph;
use App\Services\AuditService;
use App\Traits\ScopesToAssistantDivisions;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * OPH (Oil Palm Harvest) approval — Estate Manager (all divisions) or
 * Assistant Manager (division-scoped). Reviews actual harvest chits.
 */
class OphApprovalController extends BaseController
{
    use ScopesToAssistantDivisions;

    public function index(Request $request): View
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('oph_approval_date', Carbon::today()->toDateString()));
        session(['oph_approval_date' => $date->toDateString()]);

        $divisions = $this->approverDivisions();
        $noAccess  = $this->hasNoApproverDivisions($divisions);

        $build = fn (string $scope) => $noAccess ? collect()
            : Oph::actual()
                ->forDate($date->toDateString())
                ->inDivisions($divisions)
                ->{$scope}()
                ->orderBy('division_code')->orderBy('block_code')
                ->get();

        return view('approval.oph.index', [
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

        $oph = Oph::actual()->inDivisions($divisions)->where('id', $id)->first();

        if (! $oph) {
            return redirect()->route('approval.oph.index')
                ->with('error', 'OPH record not found or outside your approval scope.');
        }

        return view('approval.oph.detail', [
            'oph'     => $oph,
            'persons' => $oph->persons()->orderBy('id')->get(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $request->validate([
            'approval_type' => 'required|in:approved,rejected',
            'oph_ids'       => 'required|array|min:1',
            'oph_ids.*'     => 'required|string',
            'date'          => 'required|date',
        ]);

        $ids  = array_values(array_unique($validated['oph_ids']));
        $type = $validated['approval_type'];
        $divisions = $this->approverDivisions();

        if ($this->hasNoApproverDivisions($divisions)) {
            return back()->with('error', 'You do not have access to any division.');
        }

        $target = Oph::actual()
            ->whereIn('id', $ids)
            ->forDate($validated['date'])
            ->inDivisions($divisions)
            ->pending()
            ->get();

        if ($target->count() !== count($ids)) {
            return back()->with('error', 'Some OPH records are no longer available for approval.');
        }

        $approved = $type === 'approved';

        DB::transaction(function () use ($target, $approved) {
            foreach ($target as $oph) {
                $oph->update([
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
            ucfirst($type) . ' ' . count($ids) . ' OPH record(s)'
        );

        return redirect()->route('approval.oph.index', ['date' => $validated['date']])
            ->with('success', 'OPH ' . $type . ' successfully.');
    }
}
