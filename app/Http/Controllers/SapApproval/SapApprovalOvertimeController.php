<?php
namespace App\Http\Controllers\SapApproval;

class SapApprovalOvertimeController extends BaseApprovalController
{
    protected function table(): string         { return 't_overtime'; }
    protected function pkColumn(): string      { return 'id'; }
    protected function dateColumn(): string    { return 'overtime_date'; }
    protected function approvalColumn(): string   { return 'closing_is_approved'; }
    protected function approvedByColumn(): string { return 'closing_approved_by'; }
    protected function approvedAtColumn(): string { return 'closing_approved_at'; }
    protected function routePrefix(): string   { return 'sap-approval.overtime'; }
    protected function viewPrefix(): string    { return 'sap-approval.overtime'; }
    protected function title(): string         { return 'Work Overtime'; }
}
