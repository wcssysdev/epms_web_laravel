<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\CoconutOph;
use App\Services\AuditService;
use App\Traits\ScopesToAssistantDivisions;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Harvesting Chit (Coconut) approval — Estate Manager (all divisions) or
 * Assistant Manager (division-scoped). Only available on coconut-enabled companies.
 */
class CoconutHarvestingChitApprovalController extends BaseController
{
    use ScopesToAssistantDivisions;

    public function index(Request $request): View
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('coconut_chit_approval_date', Carbon::today()->toDateString()));
        session(['coconut_chit_approval_date' => $date->toDateString()]);

        $divisions = $this->approverDivisions();
        $noAccess  = $this->hasNoApproverDivisions($divisions);

        $build = fn (string $scope) => $noAccess ? collect()
            : CoconutOph::actual()
                ->forDate($date->toDateString())
                ->inDivisions($divisions)
                ->{$scope}()
                ->orderBy('division_code')->orderBy('block_code')
                ->get();

        return view('approval.coconut_chit.index', [
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

        $chit = CoconutOph::actual()->inDivisions($divisions)->where('id', $id)->first();

        if (! $chit) {
            return redirect()->route('approval.coconut_chit.index')
                ->with('error', 'Coconut harvesting chit not found or outside your approval scope.');
        }

        return view('approval.coconut_chit.detail', [
            'chit'    => $chit,
            'details' => $chit->details()->orderBy('id')->get(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $request->validate([
            'approval_type'      => 'required|in:approved,rejected',
            'coconut_oph_ids'    => 'required|array|min:1',
            'coconut_oph_ids.*'  => 'required|string',
            'date'               => 'required|date',
        ]);

        $ids  = array_values(array_unique($validated['coconut_oph_ids']));
        $type = $validated['approval_type'];
        $divisions = $this->approverDivisions();

        if ($this->hasNoApproverDivisions($divisions)) {
            return back()->with('error', 'You do not have access to any division.');
        }

        $target = CoconutOph::actual()
            ->whereIn('id', $ids)
            ->forDate($validated['date'])
            ->inDivisions($divisions)
            ->pending()
            ->get();

        if ($target->count() !== count($ids)) {
            return back()->with('error', 'Some coconut chits are no longer available for approval.');
        }

        $approved = $type === 'approved';

        DB::transaction(function () use ($target, $approved) {
            foreach ($target as $chit) {
                $chit->update([
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
            ucfirst($type) . ' ' . count($ids) . ' coconut harvesting chit(s)'
        );

        return redirect()->route('approval.coconut_chit.index', ['date' => $validated['date']])
            ->with('success', 'Coconut harvesting chit ' . $type . ' successfully.');
    }
}
