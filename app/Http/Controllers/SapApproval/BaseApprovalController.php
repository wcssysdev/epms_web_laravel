<?php

namespace App\Http\Controllers\SapApproval;

use App\Http\Controllers\BaseController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Base controller for SAP Closing Approval screens (Estate Manager role).
 *
 * Estate Manager reviews records that Estate Staff has locked (closing_is_approved=0)
 * and either approves (→1) or rejects (→-1) them before they can be sent to SAP.
 *
 * Index shows three buckets:
 *   pending  → closing_is_approved = 0
 *   approved → closing_is_approved = 1
 *   rejected → closing_is_approved = -1
 *
 * Actions:
 *   POST save-approval → bulk approve or reject
 */
abstract class BaseApprovalController extends BaseController
{
    abstract protected function table(): string;
    abstract protected function pkColumn(): string;
    abstract protected function dateColumn(): string;
    abstract protected function approvalColumn(): string;    // e.g. 'closing_is_approved'
    abstract protected function approvedByColumn(): string;  // e.g. 'closing_approved_by'
    abstract protected function approvedAtColumn(): string;  // e.g. 'closing_approved_at'
    abstract protected function routePrefix(): string;
    abstract protected function viewPrefix(): string;
    abstract protected function title(): string;

    /** Extra query conditions for the index (e.g. filter by estate). */
    protected function extraConditions(): array { return []; }

    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request);

        return view($this->viewPrefix() . '.index', [
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'date'        => $date,
            'pending'     => $this->queryByApproval($date, 0),
            'approved'    => $this->queryByApproval($date, 1),
            'rejected'    => $this->queryByApproval($date, -1),
        ]);
    }

    // ── SAVE APPROVAL ─────────────────────────────────────────────────────────
    public function saveApproval(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'           => 'required|array',
            'approval_type' => 'required|in:approved,rejected',
        ]);

        $date   = $request->input('date', today()->toDateString());
        $ids    = $request->input('ids');
        $status = $request->input('approval_type') === 'approved' ? 1 : -1;

        $update = [
            $this->approvalColumn()    => $status,
            $this->approvedByColumn()  => $this->userName(),
            $this->approvedAtColumn()  => Carbon::now(),
        ];

        // CI4: if integration_status=5 and adjustment_status=2 → set adjustment_status=3
        DB::table($this->table())
            ->whereIn($this->pkColumn(), $ids)
            ->each(function ($row) use ($update) {
                $extra = [];
                if (($row->integration_status ?? null) == 5 && ($row->adjustment_status ?? null) == 2) {
                    $extra['adjustment_status'] = 3;
                }
                DB::table($this->table())
                    ->where($this->pkColumn(), $row->{$this->pkColumn()})
                    ->update(array_merge($update, $extra));
            });

        $label = $status === 1 ? 'approved' : 'rejected';
        return redirect()->route($this->routePrefix() . '.index', ['date' => $date])
            ->with('success', count($ids) . ' record(s) ' . $label . '.');
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────
    protected function queryByApproval(string $date, int $status): array
    {
        // PostgreSQL: closing_is_approved may be BOOLEAN (from CI3 schema).
        // -1 = rejected, 0 = pending, 1 = approved.
        $q = DB::table($this->table())
            ->where($this->dateColumn(), Carbon::parse($date)->toDateString())
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->when(! empty($this->extraConditions()), fn($q) => $q->where($this->extraConditions()));

        // Cast for boolean columns: PostgreSQL rejects -1 for boolean.
        // Use whereRaw for explicit casting.
        if ($status === -1) {
            $q->whereRaw("CAST({$this->approvalColumn()} AS INTEGER) = -1");
        } else {
            $q->where($this->approvalColumn(), $status);
        }

        return $q->orderBy('employee_code')->get()->toArray();
    }

    protected function resolveDate(Request $request): array
    {
        $key  = $this->routePrefix() . '.date';
        $date = $request->query('date', session($key, today()->toDateString()));
        try { $date = Carbon::parse($date)->toDateString(); } catch (\Throwable $e) { $date = today()->toDateString(); }
        session([$key => $date]);
        return [$date];
    }
}
