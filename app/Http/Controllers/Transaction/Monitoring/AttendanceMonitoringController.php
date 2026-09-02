<?php

namespace App\Http\Controllers\Transaction\Monitoring;

use App\Models\Transaction\Attendance;
use Illuminate\Database\Eloquent\Builder;

class AttendanceMonitoringController extends BaseMonitoringController
{
    protected function baseQuery(): Builder { return Attendance::query()->orderByDesc('attendance_date'); }
    protected function dateColumn(): string { return 'attendance_date'; }
    protected function viewPrefix(): string { return 'transaction.monitoring.attendance'; }
    protected function routePrefix(): string { return 'transactions.monitoring.attendance'; }
    protected function title(): string { return 'Attendance Monitoring'; }
    // t_attendance has no division_code column — not division-scoped.
    protected function divisionScoped(): bool { return false; }

    protected function datatableColumns(): array
    {
        return [
            'attendance_date'      => 'Date',
            'employee_code'        => 'Employee Code',
            'employee_name'        => 'Employee',
            'mandor_employee_name' => 'Mandor',
            'attendance_code'      => 'Code',
            'attendance_desc'      => 'Description',
        ];
    }
}
