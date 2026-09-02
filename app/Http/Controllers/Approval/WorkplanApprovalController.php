<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\Workplan;
use App\Models\Transaction\WorkplanApprovalLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Workplan approval module — Estate Manager (level 40).
 *
 * Estate Managers review published workplans grouped by division/date/creator,
 * then approve or reject the whole batch. Every decision is logged.
 */
class WorkplanApprovalController extends BaseController
{
    // ── INDEX (grouped summary) ───────────────────────────────────────────────
    public function index(Request $request): View
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('workplan_approval_date', Carbon::tomorrow()->toDateString()));
        session(['workplan_approval_date' => $date->toDateString()]);

        return view('approval.workplan.index', [
            'date'      => $date,
            'published' => $this->groupedSummary($date->toDateString(), Workplan::STATUS_PUBLISHED),
            'approved'  => $this->groupedSummary($date->toDateString(), Workplan::STATUS_APPROVED),
            'rejected'  => $this->groupedSummary($date->toDateString(), Workplan::STATUS_REJECTED),
        ]);
    }

    // ── DETAIL (list of workplans in a group) ─────────────────────────────────
    public function detail(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'workplan_date'  => 'required|date',
            'division_code'  => 'required|string',
            'created_by'     => 'required|string',
            'type'           => 'required|in:published,approved,rejected',
        ]);

        $status = $this->statusFromType($validated['type']);

        $workplans = Workplan::forDate($validated['workplan_date'])
            ->byDivision($validated['division_code'])
            ->createdBy($validated['created_by'])
            ->status($status)
            ->with(['materials'])
            ->orderBy('activity_code')
            ->get();

        if ($workplans->isEmpty()) {
            return redirect()->route('approval.workplan.index')
                ->with('error', 'No workplans found for the selected group.');
        }

        // Attach latest approval remark per workplan
        $workplans->each(function ($wp) {
            $wp->last_remark = $wp->approvalLogs()->latest('id')->value('approval_remark') ?? '';
        });

        return view('approval.workplan.detail', [
            'workplans'    => $workplans,
            'date'         => $validated['workplan_date'],
            'divisionCode' => $validated['division_code'],
            'createdBy'    => $validated['created_by'],
            'type'         => $validated['type'],
        ]);
    }

    // ── SUBMIT (approve / reject batch) ────────────────────────────────────────
    public function submit(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $request->validate([
            'approval_type'          => 'required|in:approved,rejected',
            'workplan_ids'           => 'required|array|min:1',
            'workplan_ids.*'         => 'required|string|max:100',
            'remarks'                => 'required|array',
            'remarks.*'              => 'nullable|string|max:255',
            'date'                   => 'required|date',
            'division'               => 'required|string',
            'created_by'             => 'required|string',
        ]);

        $ids     = array_values($validated['workplan_ids']);
        $remarks = $validated['remarks'];
        $type    = $validated['approval_type'];

        // Remarks count must match IDs
        if (count($remarks) !== count($ids)) {
            return $this->errorBack($validated, 'Workplan remarks are invalid.');
        }

        // No duplicate IDs
        if (count($ids) !== count(array_unique($ids))) {
            return $this->errorBack($validated, 'Duplicate workplan IDs detected.');
        }

        // Rejection requires a remark on every row
        if ($type === 'rejected') {
            foreach ($remarks as $r) {
                if (trim((string) $r) === '') {
                    return $this->errorBack($validated, 'Please provide a note for each rejected workplan.');
                }
            }
        }

        // Fetch matching published workplans (only published are approvable)
        $target = Workplan::whereIn('id', $ids)
            ->forDate($validated['date'])
            ->byDivision($validated['division'])
            ->createdBy($validated['created_by'])
            ->published()
            ->get()
            ->keyBy('id');

        if ($target->count() !== count($ids)) {
            return $this->errorBack($validated, 'Some workplans are no longer available for approval.');
        }

        $status     = $this->statusFromType($type);
        $userId     = (string) $this->userId();
        $userName   = $this->userName();
        $companyId  = $this->companyId();

        DB::transaction(function () use ($ids, $remarks, $target, $status, $userId, $userName, $companyId) {
            foreach ($ids as $index => $id) {
                $note = trim((string) $remarks[$index]);

                $update = [
                    'is_approved'     => $status,
                    'approval_remark' => $note,
                    'updated_by'      => $userName,
                ];
                if ($status === Workplan::STATUS_APPROVED) {
                    $update['approved_by']      = $userId;
                    $update['approved_by_name'] = $userName;
                    $update['approved_at']      = now();
                }

                $target[$id]->update($update);

                WorkplanApprovalLog::create([
                    'company_id'       => $companyId,
                    'workplan_id'      => $id,
                    'approval_status'  => $status,
                    'approval_remark'  => $note,
                    'approved_by'      => $userId,
                    'approved_by_name' => $userName,
                    'created_by'       => $userName,
                    'updated_by'       => $userName,
                ]);
            }
        });

        AuditService::log(
            AuditService::TYPE_APPROVAL,
            $type === 'approved' ? AuditService::ACTION_APPROVE : AuditService::ACTION_REJECT,
            ucfirst($type) . ' ' . count($ids) . ' workplan(s) for division ' . $validated['division']
        );

        return redirect()->route('approval.workplan.index')
            ->with('success', 'Workplan approval saved successfully.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ════════════════════════════════════════════════════════════════════════

    /** Grouped summary: count of workplans per division/date/creator for a status. */
    private function groupedSummary(string $date, int $status)
    {
        return Workplan::forDate($date)
            ->status($status)
            ->select(
                'division_code',
                'workplan_date',
                'created_by',
                DB::raw('COUNT(*) as activity_count')
            )
            ->groupBy('division_code', 'workplan_date', 'created_by')
            ->orderBy('division_code')
            ->get();
    }

    private function statusFromType(string $type): int
    {
        return match ($type) {
            'approved'  => Workplan::STATUS_APPROVED,
            'rejected'  => Workplan::STATUS_REJECTED,
            default     => Workplan::STATUS_PUBLISHED,
        };
    }

    private function errorBack(array $data, string $message): RedirectResponse
    {
        return redirect()->route('approval.workplan.detail', [
            'workplan_date' => $data['date'] ?? '',
            'division_code' => $data['division'] ?? '',
            'created_by'    => $data['created_by'] ?? '',
            'type'          => 'published',
        ])->with('error', $message);
    }
}
