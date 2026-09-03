<?php

namespace App\Http\Controllers\Admin\Grouping;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class MandorEmployeeController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_field_staff_kemandoran'; }
    protected function resourceName(): string { return 'Mandor Employee'; }
    protected function viewPrefix(): string   { return 'admin.grouping.mandor_employee'; }
    protected function routePrefix(): string  { return 'grouping.mandor_employee'; }

    protected function datatableColumns(): array
    {
        return [
            'mandor_employee_code'      => 'Mandor Code',
            'mandor_employee_name'      => 'Mandor Name',
            'field_staff_employee_code' => 'Field Staff Code',
            'field_staff_employee_name' => 'Field Staff Name',
            'updated_at'                => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'mandor_employee_code'      => 'required|string|max:100',
            'mandor_employee_name'      => 'required|string|max:150',
            'field_staff_employee_code' => 'required|string|max:100',
            'field_staff_employee_name' => 'required|string|max:150',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'mandor_employee_code'      => strtoupper(trim($request->mandor_employee_code)),
            'mandor_employee_name'      => trim($request->mandor_employee_name),
            'field_staff_employee_code' => strtoupper(trim($request->field_staff_employee_code)),
            'field_staff_employee_name' => trim($request->field_staff_employee_name),
        ];
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('mandor_employee_code');
        if ($request->filled('mandor_code')) $query->where('mandor_employee_code', $request->mandor_code);
        return DataTables::query($query)->addIndexColumn()->make(true);
    }
}
