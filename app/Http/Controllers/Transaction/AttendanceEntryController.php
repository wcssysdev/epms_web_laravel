<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Attendance;
use App\Models\Global\Attendance as AttendanceType;
use Illuminate\Http\Request;

/**
 * Attendance entry (t_attendance) — Estate Staff operational CRUD.
 * Employee/gang based (no division column on the table).
 */
class AttendanceEntryController extends BaseTransactionController
{
    protected function modelClass(): string   { return Attendance::class; }
    protected function dateColumn(): string    { return 'attendance_date'; }
    protected function viewPrefix(): string    { return 'transaction.attendance'; }
    protected function routePrefix(): string   { return 'transactions.attendance'; }
    protected function title(): string         { return 'Attendance'; }

    protected function datatableColumns(): array
    {
        return [
            'attendance_date'      => 'Date',
            'employee_code'        => 'Employee Code',
            'employee_name'        => 'Employee',
            'attendance_code'      => 'Attendance',
            'gang_allotment_code'  => 'Gang',
            'mandor_employee_name' => 'Mandor',
        ];
    }

    protected function rules(Request $request): array
    {
        return [
            'attendance_date'      => 'required|date',
            'employee_code'        => 'required|string|max:100',
            'attendance_code'      => 'required|string|max:50',
            'gang_allotment_code'  => 'nullable|string|max:100',
            'mandor_employee_code' => 'nullable|string|max:100',
            'work_status'          => 'nullable|integer',
            'remark'               => 'nullable|string|max:255',
        ];
    }

    protected function mapRow(Request $request): array
    {
        $emp    = \App\Models\Master\Employee::where('employee_code', $request->employee_code)->first();
        $mandor = $request->mandor_employee_code
            ? \App\Models\Master\Employee::where('employee_code', $request->mandor_employee_code)->first()
            : null;
        $type   = AttendanceType::where('attendance_code', $request->attendance_code)->first();

        return [
            'attendance_date'      => $request->attendance_date,
            'employee_code'        => trim($request->employee_code),
            'employee_name'        => $emp?->employee_name ?? '',
            'attendance_code'      => trim($request->attendance_code),
            'attendance_desc'      => $type?->attendance_desc ?? '',
            'work_status'          => $request->work_status !== null ? (int) $request->work_status : null,
            'gang_allotment_code'  => $request->gang_allotment_code ?: null,
            'mandor_employee_code' => $request->mandor_employee_code ?: null,
            'mandor_employee_name' => $mandor?->employee_name,
            'remark'               => $request->remark ?: null,
        ];
    }

    protected function formData(): array
    {
        return [
            'employees'       => $this->employees(),
            'attendanceTypes' => AttendanceType::orderBy('attendance_code')
                                    ->get(['attendance_code', 'attendance_desc']),
        ];
    }
}
