<?php
namespace App\Http\Controllers\Closing;

use Illuminate\Support\Facades\DB;

class ClosingAttendanceController extends BaseClosingController
{
    protected function table(): string       { return 't_attendance'; }
    protected function pkColumn(): string    { return 'id'; }
    protected function dateColumn(): string  { return 'attendance_date'; }
    protected function sapUrn(): string      { return 'urn:ZEPMS_ATTENDANCE_IN'; }
    protected function sapIm(): string       { return 'IM_ATTD'; }
    protected function routePrefix(): string { return 'closing.attendance'; }
    protected function viewPrefix(): string  { return 'closing.attendance'; }
    protected function title(): string       { return 'Attendance'; }
    protected function stripEstatePrefix(): bool        { return true; }
    protected function requiresClosingApproval(): bool  { return true; }

    protected function buildSapItems(array $rows, array $config): array
    {
        $estate = $config['estate_code'] ?? '';
        $reqId  = \App\Services\SapService::requestId($this->userId());
        $items  = [];
        foreach ($rows as $r) {
            // Join employee profile
            $emp = DB::table('m_employee')->where('employee_code', $r['employee_code'] ?? '')->first();
            $offst = ($r['work_status'] ?? 0) == 1 ? 'OFF' : null;
            $items[] = [
                'EMPNR_M'    => $r['mandor_employee_code'] ?? null,
                'EMPNR'      => $r['employee_code'] ?? null,
                'BUDAT'      => $r['attendance_date'] ?? null,
                'ATDCD'      => $r['attendance_code'] ?? null,
                'REQUEST_ID' => $reqId,
                'UNIQUE_ID'  => $estate . $r['id'],  // prepend estate — stripped back by update_integration_result
                'OFFST'      => $offst,
                'PRFNR'      => $emp?->employee_profile ?? null,
                'DEPNR'      => $r['gang_allotment_code'] ?? null,
                'STATE'      => 'C',
            ];
        }
        return $items;
    }
}
