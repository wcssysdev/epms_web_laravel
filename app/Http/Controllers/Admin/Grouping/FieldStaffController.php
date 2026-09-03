<?php

namespace App\Http\Controllers\Admin\Grouping;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FieldStaffController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_field_staff_gang'; }
    protected function resourceName(): string { return 'Field Staff'; }
    protected function viewPrefix(): string   { return 'admin.grouping.field_staff'; }
    protected function routePrefix(): string  { return 'grouping.field_staff'; }

    protected function datatableColumns(): array
    {
        return [
            'field_staff_gang_code'     => 'Gang Code',
            'field_staff_employee_code' => 'Employee Code',
            'field_staff_employee_name' => 'Employee Name',
            'updated_at'                => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'field_staff_gang_code'     => 'required|string|max:50',
            'field_staff_employee_code' => 'required|string|max:100',
            'field_staff_employee_name' => 'required|string|max:150',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'field_staff_gang_code'     => strtoupper(trim($request->field_staff_gang_code)),
            'field_staff_employee_code' => strtoupper(trim($request->field_staff_employee_code)),
            'field_staff_employee_name' => trim($request->field_staff_employee_name),
        ];
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('field_staff_gang_code')->orderBy('field_staff_employee_code');
        if ($request->filled('gang_code')) $query->where('field_staff_gang_code', $request->gang_code);
        return DataTables::query($query)->addIndexColumn()->make(true);
    }
}
