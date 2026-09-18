<?php

namespace App\Http\Controllers\Admin\Grouping;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Transaction\User;
use App\Models\Global\Role;

class FieldAssistantDivisionController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_assistant_manager_division'; }
    protected function resourceName(): string { return 'Grouping Assistant Manager - Division'; }
    protected function viewPrefix(): string   { return 'admin.grouping.field_assistant_division'; }
    protected function routePrefix(): string  { return 'grouping.field_assistant_division'; }

    protected function datatableColumns(): array
    {
        return [
            'assistant_manager_code' => 'Assistant Manager ID',
            'assistant_manager_name' => 'Assistant Manager Name',
            'division_code'          => 'Division Code',
            'division_name'          => 'Division Name',
            'created_date_display'   => 'Created Date',
            'updated_date_display'   => 'Updated Date',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'assistant_manager_code' => 'required|string',
            'division_code'          => 'required|string',
        ];
    }

    protected function storeData(Request $request): array
    {
        $asstUser = User::where('id', $request->assistant_manager_code)
            ->orWhere('user_internal_employee_code', $request->assistant_manager_code)
            ->orWhere('username', $request->assistant_manager_code)
            ->first();

        $asstName = $asstUser?->user_name ?? ($asstUser?->username ?? $request->assistant_manager_code);
        $asstCode = $asstUser?->user_internal_employee_code ?: ($asstUser?->id ?? $request->assistant_manager_code);

        $division = DB::table('m_division')
            ->where('division_code', $request->division_code)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->first();

        $divName = $division?->division_name ?? $request->division_code;

        return [
            'assistant_manager_code' => $asstCode,
            'assistant_manager_name' => $asstName,
            'division_code'          => strtoupper(trim($request->division_code)),
            'division_name'          => $divName,
        ];
    }

    public function create()
    {
        $companyId = $this->companyId();

        // Get Assistant Managers (role level 50 / asst_manager) for this company
        $assistantManagers = User::whereHas('access', function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->whereHas('role', fn($rq) => $rq->where('level', Role::ASST_MANAGER)->orWhere('role_code', 'asst_manager'));
        })->get(['id', 'username', 'user_name', 'user_internal_employee_code']);

        // If none found with strict role, fallback to active users in this company
        if ($assistantManagers->isEmpty()) {
            $assistantManagers = User::whereHas('access', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->get(['id', 'username', 'user_name', 'user_internal_employee_code']);
        }

        // Get Divisions for this company
        $divisions = DB::table('m_division')
            ->where('company_id', $companyId)
            ->orderBy('division_name')
            ->get(['division_code', 'division_name']);

        return view($this->viewPrefix() . '.form', [
            'resourceName'      => $this->resourceName(),
            'routePrefix'       => $this->routePrefix(),
            'item'              => null,
            'assistantManagers' => $assistantManagers,
            'divisions'         => $divisions,
        ]);
    }

    public function edit(int $id)
    {
        $item = $this->baseQuery()->where('id', $id)->first();
        abort_unless($item, 404);

        $companyId = $this->companyId();

        $assistantManagers = User::whereHas('access', function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->whereHas('role', fn($rq) => $rq->where('level', Role::ASST_MANAGER)->orWhere('role_code', 'asst_manager'));
        })->get(['id', 'username', 'user_name', 'user_internal_employee_code']);

        if ($assistantManagers->isEmpty()) {
            $assistantManagers = User::whereHas('access', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->get(['id', 'username', 'user_name', 'user_internal_employee_code']);
        }

        $divisions = DB::table('m_division')
            ->where('company_id', $companyId)
            ->orderBy('division_name')
            ->get(['division_code', 'division_name']);

        return view($this->viewPrefix() . '.form', [
            'resourceName'      => $this->resourceName(),
            'routePrefix'       => $this->routePrefix(),
            'item'              => $item,
            'assistantManagers' => $assistantManagers,
            'divisions'         => $divisions,
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('assistant_manager_code')->orderBy('division_code');
        if ($request->filled('manager_code')) $query->where('assistant_manager_code', $request->manager_code);
        if ($request->filled('division_code')) $query->where('division_code', $request->division_code);

        return DataTables::query($query)
            ->addIndexColumn()
            ->addColumn('created_date_display', function ($row) {
                return $row->created_at ? substr((string)$row->created_at, 0, 19) : '-';
            })
            ->addColumn('updated_date_display', function ($row) {
                return $row->updated_at ? substr((string)$row->updated_at, 0, 19) : '-';
            })
            ->rawColumns(['created_date_display', 'updated_date_display'])
            ->make(true);
    }
}
