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
            'assistant_manager_code' => 'required',
            'division_code'          => 'required|string',
        ];
    }

    protected function storeData(Request $request): array
    {
        // Not used directly because store() is overridden to match CI3 exact validation & messages
        return [];
    }

    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $request->validate($this->storeValidation(), [
            'assistant_manager_code.required' => 'Assistant Manager is required.',
            'division_code.required'          => 'Division is required.',
        ]);

        $companyId  = $this->companyId();
        $estateCode = $this->estateCode();
        $inputVal   = $request->assistant_manager_code;

        // 1. Find user in tc_user safely (handling numeric ID vs string code/username without SQL cast errors)
        $userQuery = User::query();
        if (is_numeric($inputVal)) {
            $userQuery->where(function ($q) use ($inputVal) {
                $q->where('id', (int) $inputVal)
                  ->orWhere('user_internal_employee_code', (string) $inputVal)
                  ->orWhere('username', (string) $inputVal);
            });
        } else {
            $userQuery->where(function ($q) use ($inputVal) {
                $q->where('user_internal_employee_code', (string) $inputVal)
                  ->orWhere('username', (string) $inputVal);
            });
        }
        $asstUser = $userQuery->first();

        if (!$asstUser) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Assistant Manager not found.');
        }

        $asstCode = (string) $asstUser->id;
        $asstName = $asstUser->user_name ?: ($asstUser->username ?: 'Assistant Manager');

        // 2. Find division in m_division
        $division = DB::table('m_division')
            ->where('division_code', $request->division_code)
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($estateCode, fn($q) => $q->where('estate_code', $estateCode))
            ->first();

        if (!$division) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Division not found.');
        }

        $divCode = $division->division_code;
        $divName = $division->division_name;

        // 3. Validation: CI3 Duplicate Check
        $checkExist = $this->baseQuery()
            ->where('assistant_manager_code', $asstCode)
            ->where('division_code', $divCode)
            ->first();

        if ($checkExist) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Duplicate Assistant Manager and Division');
        }

        // 4. Insert data
        $insertData = [
            'company_id'             => $companyId,
            'assistant_manager_code' => $asstCode,
            'assistant_manager_name' => $asstName,
            'division_code'          => $divCode,
            'division_name'          => $divName,
            'created_by'             => $this->userName(),
            'updated_by'             => $this->userName(),
            'created_at'             => now(),
            'updated_at'             => now(),
        ];

        DB::table($this->tableName())->insert($insertData);

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', 'Assistant Manager - Division successfully saved');
    }

    public function create()
    {
        $companyId  = $this->companyId();
        $estateCode = $this->estateCode();

        // Get Assistant Managers (role level 50 / asst_manager) for this company
        $assistantManagers = User::whereHas('access', function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->whereHas('role', fn($rq) => $rq->where('level', Role::ASST_MANAGER)->orWhere('role_code', 'asst_manager'));
        })->get(['id', 'username', 'user_name', 'user_internal_employee_code']);

        // Fallback if none configured with strict role level 50
        if ($assistantManagers->isEmpty()) {
            $assistantManagers = User::whereHas('access', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->get(['id', 'username', 'user_name', 'user_internal_employee_code']);
        }

        // Get Divisions for this estate and company
        $divisions = DB::table('m_division')
            ->where('company_id', $companyId)
            ->when($estateCode, fn($q) => $q->where('estate_code', $estateCode))
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_to', '>=', now()->toDateString())
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

        $companyId  = $this->companyId();
        $estateCode = $this->estateCode();

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
            ->when($estateCode, fn($q) => $q->where('estate_code', $estateCode))
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_to', '>=', now()->toDateString())
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

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $request->validate($this->storeValidation(), [
            'assistant_manager_code.required' => 'Assistant Manager is required.',
            'division_code.required'          => 'Division is required.',
        ]);

        $companyId  = $this->companyId();
        $estateCode = $this->estateCode();
        $inputVal   = $request->assistant_manager_code;

        $userQuery = User::query();
        if (is_numeric($inputVal)) {
            $userQuery->where(function ($q) use ($inputVal) {
                $q->where('id', (int) $inputVal)
                  ->orWhere('user_internal_employee_code', (string) $inputVal)
                  ->orWhere('username', (string) $inputVal);
            });
        } else {
            $userQuery->where(function ($q) use ($inputVal) {
                $q->where('user_internal_employee_code', (string) $inputVal)
                  ->orWhere('username', (string) $inputVal);
            });
        }
        $asstUser = $userQuery->first();

        if (!$asstUser) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Assistant Manager not found.');
        }

        $asstCode = (string) $asstUser->id;
        $asstName = $asstUser->user_name ?: ($asstUser->username ?: 'Assistant Manager');

        $division = DB::table('m_division')
            ->where('division_code', $request->division_code)
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($estateCode, fn($q) => $q->where('estate_code', $estateCode))
            ->first();

        if (!$division) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Division not found.');
        }

        $divCode = $division->division_code;
        $divName = $division->division_name;

        // Check duplicate excluding self
        $checkExist = $this->baseQuery()
            ->where('assistant_manager_code', $asstCode)
            ->where('division_code', $divCode)
            ->where('id', '!=', $id)
            ->first();

        if ($checkExist) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Duplicate Assistant Manager and Division');
        }

        $this->baseQuery()->where('id', $id)->update([
            'assistant_manager_code' => $asstCode,
            'assistant_manager_name' => $asstName,
            'division_code'          => $divCode,
            'division_name'          => $divName,
            'updated_by'             => $this->userName(),
            'updated_at'             => now(),
        ]);

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', 'Assistant Manager - Division successfully updated');
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
