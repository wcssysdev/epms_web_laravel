<?php
namespace App\Http\Controllers\SapApproval;

class SapApprovalAttendanceController extends BaseApprovalController
{
    protected function table(): string         { return 't_attendance'; }
    protected function pkColumn(): string      { return 'id'; }
    protected function dateColumn(): string    { return 'attendance_date'; }
    protected function approvalColumn(): string   { return 'closing_is_approved'; }
    protected function approvedByColumn(): string { return 'closing_approved_by'; }
    protected function approvedAtColumn(): string { return 'closing_approved_at'; }
    protected function routePrefix(): string   { return 'sap-approval.attendance'; }
    protected function viewPrefix(): string    { return 'sap-approval.attendance'; }
    protected function title(): string         { return 'Attendance'; }
}
