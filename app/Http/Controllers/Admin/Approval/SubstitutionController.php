<?php

namespace App\Http\Controllers\Admin\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\ApprovalSubstitution;
use App\Models\Transaction\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class SubstitutionController extends BaseController
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ApprovalSubstitution::managerSubstitution();

            $cid = $this->companyId();
            if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
                $query->where('company_id', $cid);
            }

            $query->orderByDesc('substitution_from')->orderByDesc('substitution_to');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('employee_display', function ($row) {
                    $code = $row->employee_code ? " ({$row->employee_code})" : '';
                    return e($row->employee_name) . $code;
                })
                ->addColumn('target_employee_display', function ($row) {
                    $code = $row->target_employee_code ? " ({$row->target_employee_code})" : '';
                    return e($row->target_employee_name) . $code;
                })
                ->addColumn('substitution_from_formatted', function ($row) {
                    return $row->formatted_from;
                })
                ->addColumn('substitution_to_formatted', function ($row) {
                    return $row->formatted_to;
                })
                ->addColumn('status', function ($row) {
                    return $row->status;
                })
                ->addColumn('action', function ($row) {
                    $editUrl = route('admin.substitution.edit', $row->id);
                    $btn = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fa fa-pencil"></i></a> ';
                    $btn .= '<button type="button" class="btn btn-xs btn-danger btn-delete" data-id="' . $row->id . '" title="Delete"><i class="fa fa-trash"></i></button>';
                    return $btn;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        $systemLocked = $this->isSystemLocked();
        return view('admin.approval.substitution.index', compact('systemLocked'));
    }

    public function create()
    {
        $cid = $this->companyId();

        // Get Estate Managers (role_code: estate_manager)
        $estateManagersQuery = User::whereHas('access.role', function ($q) {
            $q->where('role_code', 'estate_manager');
        })->where('is_active', true);

        if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
            $estateManagersQuery->whereHas('access', fn($q) => $q->where('company_id', $cid));
        }
        $estateManagers = $estateManagersQuery->orderBy('user_name')->get();

        // Get Assistant Managers (role_code: asst_manager or assistant_manager)
        $assistantManagersQuery = User::whereHas('access.role', function ($q) {
            $q->whereIn('role_code', ['asst_manager', 'assistant_manager']);
        })->where('is_active', true);

        if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
            $assistantManagersQuery->whereHas('access', fn($q) => $q->where('company_id', $cid));
        }
        $assistantManagers = $assistantManagersQuery->orderBy('user_name')->get();

        $systemLocked = $this->isSystemLocked();

        return view('admin.approval.substitution.create', compact('estateManagers', 'assistantManagers', 'systemLocked'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_code'        => 'required|exists:tc_user,id',
            'target_employee_code' => 'required|exists:tc_user,id',
            'substitution_from'    => 'required|date',
            'substitution_to'      => 'required|date|after_or_equal:substitution_from',
        ]);

        if ($this->isSystemLocked()) {
            return redirect()->back()
                ->with('error', 'System is locked, please contact manager for more information.')
                ->withInput();
        }

        $sourceId = $validated['employee_code'];
        $targetId = $validated['target_employee_code'];

        if ($sourceId == $targetId) {
            return redirect()->back()
                ->with('error', "Substitution employee and substitute can't be the same.")
                ->withInput();
        }

        $source = User::find($sourceId);
        if (!$source || !in_array($source->role_code, ['estate_manager'])) {
            return redirect()->back()
                ->with('error', 'Substitution employee must be an Estate Manager.')
                ->withInput();
        }

        $target = User::find($targetId);
        if (!$target || !in_array($target->role_code, ['asst_manager', 'assistant_manager'])) {
            return redirect()->back()
                ->with('error', 'Substitute must be an Assistant Manager.')
                ->withInput();
        }

        $from = date('Y-m-d', strtotime($validated['substitution_from']));
        $to   = date('Y-m-d', strtotime($validated['substitution_to']));

        // Check for date conflicts/overlaps
        $clash = ApprovalSubstitution::managerSubstitution()
            ->where('employee_code', (string)$sourceId)
            ->where('target_employee_code', (string)$targetId)
            ->where('substitution_from', '<=', $to)
            ->where('substitution_to', '>=', $from)
            ->exists();

        if ($clash) {
            return redirect()->back()
                ->with('error', 'There is a date conflict with an existing substitution period for this employee and substitute.')
                ->withInput();
        }

        $companyId = $this->companyId() ?: ($source->company_id ?: ($target->company_id ?: 1));

        ApprovalSubstitution::create([
            'company_id'           => $companyId,
            'employee_code'        => (string)$source->id,
            'employee_name'        => $source->user_name,
            'target_employee_code' => (string)$target->id,
            'target_employee_name' => $target->user_name,
            'substitution_from'    => $from,
            'substitution_to'      => $to,
            'substitution_type'    => ApprovalSubstitution::TYPE_MANAGER,
            'created_by'           => $this->userName() ?? 'admin',
            'updated_by'           => $this->userName() ?? 'admin',
        ]);

        return redirect()->route('admin.substitution.index')
            ->with('success', 'Estate Manager substitution successfully created.');
    }

    public function edit($id)
    {
        $substitution = ApprovalSubstitution::managerSubstitution()->findOrFail($id);

        $cid = $this->companyId();

        $estateManagersQuery = User::whereHas('access.role', function ($q) {
            $q->where('role_code', 'estate_manager');
        })->where('is_active', true);

        if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
            $estateManagersQuery->whereHas('access', fn($q) => $q->where('company_id', $cid));
        }
        $estateManagers = $estateManagersQuery->orderBy('user_name')->get();

        $assistantManagersQuery = User::whereHas('access.role', function ($q) {
            $q->whereIn('role_code', ['asst_manager', 'assistant_manager']);
        })->where('is_active', true);

        if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
            $assistantManagersQuery->whereHas('access', fn($q) => $q->where('company_id', $cid));
        }
        $assistantManagers = $assistantManagersQuery->orderBy('user_name')->get();

        $systemLocked = $this->isSystemLocked();

        return view('admin.approval.substitution.edit', compact('substitution', 'estateManagers', 'assistantManagers', 'systemLocked'));
    }

    public function update(Request $request, $id)
    {
        $substitution = ApprovalSubstitution::managerSubstitution()->findOrFail($id);

        $validated = $request->validate([
            'employee_code'        => 'required|exists:tc_user,id',
            'target_employee_code' => 'required|exists:tc_user,id',
            'substitution_from'    => 'required|date',
            'substitution_to'      => 'required|date|after_or_equal:substitution_from',
        ]);

        if ($this->isSystemLocked()) {
            return redirect()->back()
                ->with('error', 'System is locked, please contact manager for more information.')
                ->withInput();
        }

        $sourceId = $validated['employee_code'];
        $targetId = $validated['target_employee_code'];

        if ($sourceId == $targetId) {
            return redirect()->back()
                ->with('error', "Substitution employee and substitute can't be the same.")
                ->withInput();
        }

        $source = User::find($sourceId);
        if (!$source || !in_array($source->role_code, ['estate_manager'])) {
            return redirect()->back()
                ->with('error', 'Substitution employee must be an Estate Manager.')
                ->withInput();
        }

        $target = User::find($targetId);
        if (!$target || !in_array($target->role_code, ['asst_manager', 'assistant_manager'])) {
            return redirect()->back()
                ->with('error', 'Substitute must be an Assistant Manager.')
                ->withInput();
        }

        $from = date('Y-m-d', strtotime($validated['substitution_from']));
        $to   = date('Y-m-d', strtotime($validated['substitution_to']));

        // Check for conflicts excluding current record
        $clash = ApprovalSubstitution::managerSubstitution()
            ->where('id', '!=', $id)
            ->where('employee_code', (string)$sourceId)
            ->where('target_employee_code', (string)$targetId)
            ->where('substitution_from', '<=', $to)
            ->where('substitution_to', '>=', $from)
            ->exists();

        if ($clash) {
            return redirect()->back()
                ->with('error', 'There is a date conflict with an existing substitution period for this employee and substitute.')
                ->withInput();
        }

        $substitution->update([
            'employee_code'        => (string)$source->id,
            'employee_name'        => $source->user_name,
            'target_employee_code' => (string)$target->id,
            'target_employee_name' => $target->user_name,
            'substitution_from'    => $from,
            'substitution_to'      => $to,
            'updated_by'           => $this->userName() ?? 'admin',
        ]);

        return redirect()->route('admin.substitution.index')
            ->with('success', 'Estate Manager substitution successfully updated.');
    }

    public function destroy($id)
    {
        if ($this->isSystemLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'System is locked, please contact manager for more information.',
            ]);
        }

        $deleted = ApprovalSubstitution::managerSubstitution()
            ->where('id', $id)
            ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Estate Manager substitution deleted successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Substitution record not found or already deleted.',
        ]);
    }
}
