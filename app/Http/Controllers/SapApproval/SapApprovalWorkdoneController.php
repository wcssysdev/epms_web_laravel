<?php
namespace App\Http\Controllers\SapApproval;

class SapApprovalWorkdoneController extends BaseApprovalController
{
    protected function table(): string         { return 't_workdone'; }
    protected function pkColumn(): string      { return 'id'; }
    protected function dateColumn(): string    { return 'workdone_date'; }
    protected function approvalColumn(): string   { return 'closing_is_approved'; }
    protected function approvedByColumn(): string { return 'closing_approved_by'; }
    protected function approvedAtColumn(): string { return 'closing_approved_at'; }
    protected function routePrefix(): string   { return 'sap-approval.workdone'; }
    protected function viewPrefix(): string    { return 'sap-approval.workdone'; }
    protected function title(): string         { return 'General Work (Workdone)'; }
}
