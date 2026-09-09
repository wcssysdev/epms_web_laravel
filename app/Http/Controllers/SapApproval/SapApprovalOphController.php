<?php
namespace App\Http\Controllers\SapApproval;

class SapApprovalOphController extends BaseApprovalController
{
    protected function table(): string         { return 't_oph'; }
    protected function pkColumn(): string      { return 'id'; }
    protected function dateColumn(): string    { return 'created_at'; }
    protected function approvalColumn(): string   { return 'closing_is_approved'; }
    protected function approvedByColumn(): string { return 'closing_approved_by'; }
    protected function approvedAtColumn(): string { return 'closing_approved_at'; }
    protected function routePrefix(): string   { return 'sap-approval.oph'; }
    protected function viewPrefix(): string    { return 'sap-approval.oph'; }
    protected function title(): string         { return 'OPH (Palm)'; }

    /** Override: created_at is TIMESTAMP, use whereDate. */
    protected function queryByApproval(string $date, int $status): array
    {
        $q = \Illuminate\Support\Facades\DB::table($this->table())
            ->whereDate($this->dateColumn(), \Carbon\Carbon::parse($date)->toDateString())
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()));

        if ($status === -1) {
            $q->whereRaw("CAST({$this->approvalColumn()} AS INTEGER) = -1");
        } else {
            $q->where($this->approvalColumn(), $status);
        }

        return $q->orderBy('id')->get()->toArray();
    }
}
