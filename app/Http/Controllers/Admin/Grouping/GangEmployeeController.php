<?php

namespace App\Http\Controllers\Admin\Grouping;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class GangEmployeeController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_gang_employee'; }
    protected function resourceName(): string { return 'Gang Employee'; }
    protected function viewPrefix(): string   { return 'admin.grouping.gang_employee'; }
    protected function routePrefix(): string  { return 'grouping.gang_employee'; }

    protected function datatableColumns(): array
    {
        return [
            'gang_code'          => 'Gang Code',
            'gang_employee_code' => 'Employee Code',
            'gang_employee_name' => 'Employee Name',
            'updated_at'         => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'gang_code'          => 'required|string|max:50',
            'gang_employee_code' => 'required|string|max:100',
            'gang_employee_name' => 'required|string|max:150',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'gang_code'          => strtoupper(trim($request->gang_code)),
            'gang_employee_code' => strtoupper(trim($request->gang_employee_code)),
            'gang_employee_name' => trim($request->gang_employee_name),
        ];
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('gang_code')->orderBy('gang_employee_code');
        if ($request->filled('gang_code')) $query->where('gang_code', $request->gang_code);
        return DataTables::query($query)->addIndexColumn()->make(true);
    }

    // Get distinct gang codes for filter dropdown
    public function gangCodes(): JsonResponse
    {
        $codes = DB::table('m_gang_employee')
            ->where('company_id', $this->companyId())
            ->distinct()->orderBy('gang_code')
            ->pluck('gang_code');
        return $this->jsonSuccess('OK', $codes);
    }
}
