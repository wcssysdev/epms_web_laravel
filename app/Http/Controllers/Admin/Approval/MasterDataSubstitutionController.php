<?php

namespace App\Http\Controllers\Admin\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\ApprovalSubstitution;
use App\Models\Transaction\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class MasterDataSubstitutionController extends BaseController
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ApprovalSubstitution::masterDataSubstitution();

            $cid = $this->companyId();
            if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
                $query->where('company_id', $cid);
            }

            $query->orderByDesc('substitution_from')->orderByDesc('substitution_to');

            return DataTables::of($query)
                ->addIndexColumn()
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
                    $editUrl = route('admin.master-data-substitution.edit', $row->id);
                    $btn = '<a href="' . $editUrl . '" class="btn btn-xs btn-warning" title="Edit"><i class="fa fa-pencil"></i></a> ';
                    $btn .= '<button type="button" class="btn btn-xs btn-danger btn-delete" data-id="' . $row->id . '" title="Delete"><i class="fa fa-trash"></i></button>';
                    return $btn;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        $systemLocked = $this->isSystemLocked();
        return view('admin.approval.master_data_substitution.index', compact('systemLocked'));
    }

    public function create()
    {
        $cid = $this->companyId();

        $usersQuery = User::with('access.role')->where('is_active', true);
        if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
            $usersQuery->whereHas('access', fn($q) => $q->where('company_id', $cid));
        }
        $users = $usersQuery->orderBy('user_name')->get();

        $systemLocked = $this->isSystemLocked();

        return view('admin.approval.master_data_substitution.create', compact('users', 'systemLocked'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_employee_code' => 'required|exists:tc_user,id',
            'substitution_from'    => 'required|date',
            'substitution_to'      => 'required|date|after_or_equal:substitution_from',
        ]);

        if ($this->isSystemLocked()) {
            return redirect()->back()
                ->with('error', 'System is locked, please contact manager for more information.')
                ->withInput();
        }

        $targetId = $validated['target_employee_code'];
        $target   = User::find($targetId);
        if (!$target) {
            return redirect()->back()
                ->with('error', 'Target user not found.')
                ->withInput();
        }

        $from = date('Y-m-d', strtotime($validated['substitution_from']));
        $to   = date('Y-m-d', strtotime($validated['substitution_to']));

        // Check for date conflicts/overlaps on existing master data substitutions
        $clash = ApprovalSubstitution::masterDataSubstitution()
            ->where('target_employee_code', (string)$targetId)
            ->where('substitution_from', '<=', $to)
            ->where('substitution_to', '>=', $from)
            ->exists();

        if ($clash) {
            return redirect()->back()
                ->with('error', 'There is a date conflict with an existing master data substitution for this user.')
                ->withInput();
        }

        $companyId = $this->companyId() ?: ($target->company_id ?: 1);

        ApprovalSubstitution::create([
            'company_id'           => $companyId,
            'employee_code'        => null,
            'employee_name'        => null,
            'target_employee_code' => (string)$target->id,
            'target_employee_name' => $target->user_name,
            'substitution_from'    => $from,
            'substitution_to'      => $to,
            'substitution_type'    => ApprovalSubstitution::TYPE_MASTER_DATA,
            'created_by'           => $this->userName() ?? 'admin',
            'updated_by'           => $this->userName() ?? 'admin',
        ]);

        return redirect()->route('admin.master-data-substitution.index')
            ->with('success', 'Master data substitution successfully created.');
    }

    public function edit($id)
    {
        $substitution = ApprovalSubstitution::masterDataSubstitution()->findOrFail($id);

        $cid = $this->companyId();

        $usersQuery = User::with('access.role')->where('is_active', true);
        if ($cid && !$this->isSuperAdmin() && !$this->isCountryAdmin()) {
            $usersQuery->whereHas('access', fn($q) => $q->where('company_id', $cid));
        }
        $users = $usersQuery->orderBy('user_name')->get();

        $systemLocked = $this->isSystemLocked();

        return view('admin.approval.master_data_substitution.edit', compact('substitution', 'users', 'systemLocked'));
    }

    public function update(Request $request, $id)
    {
        $substitution = ApprovalSubstitution::masterDataSubstitution()->findOrFail($id);

        $validated = $request->validate([
            'target_employee_code' => 'required|exists:tc_user,id',
            'substitution_from'    => 'required|date',
            'substitution_to'      => 'required|date|after_or_equal:substitution_from',
        ]);

        if ($this->isSystemLocked()) {
            return redirect()->back()
                ->with('error', 'System is locked, please contact manager for more information.')
                ->withInput();
        }

        $targetId = $validated['target_employee_code'];
        $target   = User::find($targetId);
        if (!$target) {
            return redirect()->back()
                ->with('error', 'Target user not found.')
                ->withInput();
        }

        $from = date('Y-m-d', strtotime($validated['substitution_from']));
        $to   = date('Y-m-d', strtotime($validated['substitution_to']));

        // Check for conflicts excluding current record
        $clash = ApprovalSubstitution::masterDataSubstitution()
            ->where('id', '!=', $id)
            ->where('target_employee_code', (string)$targetId)
            ->where('substitution_from', '<=', $to)
            ->where('substitution_to', '>=', $from)
            ->exists();

        if ($clash) {
            return redirect()->back()
                ->with('error', 'There is a date conflict with an existing master data substitution for this user.')
                ->withInput();
        }

        $substitution->update([
            'target_employee_code' => (string)$target->id,
            'target_employee_name' => $target->user_name,
            'substitution_from'    => $from,
            'substitution_to'      => $to,
            'updated_by'           => $this->userName() ?? 'admin',
        ]);

        return redirect()->route('admin.master-data-substitution.index')
            ->with('success', 'Master data substitution successfully updated.');
    }

    public function destroy($id)
    {
        if ($this->isSystemLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'System is locked, please contact manager for more information.',
            ]);
        }

        $deleted = ApprovalSubstitution::masterDataSubstitution()
            ->where('id', $id)
            ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Master data substitution deleted successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Substitution record not found or already deleted.',
        ]);
    }
}
