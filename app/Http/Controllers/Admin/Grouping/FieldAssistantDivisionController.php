<?php

namespace App\Http\Controllers\Admin\Grouping;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FieldAssistantDivisionController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_assistant_manager_division'; }
    protected function resourceName(): string { return 'Field Assistant Division'; }
    protected function viewPrefix(): string   { return 'admin.grouping.field_assistant_division'; }
    protected function routePrefix(): string  { return 'grouping.field_assistant_division'; }

    protected function datatableColumns(): array
    {
        return [
            'assistant_manager_code' => 'Asst. Manager Code',
            'assistant_manager_name' => 'Asst. Manager Name',
            'division_code'          => 'Division Code',
            'division_name'          => 'Division Name',
            'updated_at'             => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'assistant_manager_code' => 'required|string|max:100',
            'assistant_manager_name' => 'required|string|max:150',
            'division_code'          => 'required|string|max:50',
            'division_name'          => 'required|string|max:150',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'assistant_manager_code' => strtoupper(trim($request->assistant_manager_code)),
            'assistant_manager_name' => trim($request->assistant_manager_name),
            'division_code'          => strtoupper(trim($request->division_code)),
            'division_name'          => trim($request->division_name),
        ];
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('assistant_manager_code')->orderBy('division_code');
        if ($request->filled('manager_code')) $query->where('assistant_manager_code', $request->manager_code);
        if ($request->filled('division_code')) $query->where('division_code', $request->division_code);
        return DataTables::queryBuilder($query)->addIndexColumn()->make(true);
    }

    // Get managers dropdown
    public function managers(): JsonResponse
    {
        $managers = DB::table('m_assistant_manager_division')
            ->where('company_id', $this->companyId())
            ->distinct()->orderBy('assistant_manager_code')
            ->get(['assistant_manager_code', 'assistant_manager_name']);
        return $this->jsonSuccess('OK', $managers);
    }
}
