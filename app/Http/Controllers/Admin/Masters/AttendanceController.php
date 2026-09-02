<?php

namespace App\Http\Controllers\Admin\Masters;

class AttendanceController extends BaseGlobalLookupController
{
    protected function tableName(): string    { return 'm_attendance'; }
    protected function resourceName(): string { return 'Attendance'; }
    protected function primaryKey(): string   { return 'id'; }
    protected function viewPrefix(): string   { return 'admin.masters.global.attendance'; }
    protected function routePrefix(): string  { return 'masters.global.attendance'; }

    protected function datatableColumns(): array
    {
        return ['attendance_code' => 'Code', 'attendance_desc' => 'Description', 'updated_at' => 'Last Updated'];
    }

    protected function csvHeaders(): array
    {
        return ['attendance_code', 'attendance_desc'];
    }

    protected function formFields(): array
    {
        return ['attendance_code' => 'required|string|max:50', 'attendance_desc' => 'required|string|max:255'];
    }

    protected function mapRow(array $data): array
    {
        return ['attendance_code' => strtoupper(trim($data['attendance_code'])), 'attendance_desc' => trim($data['attendance_desc'])];
    }
}
