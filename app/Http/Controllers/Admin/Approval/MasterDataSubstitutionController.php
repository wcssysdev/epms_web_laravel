<?php

namespace App\Http\Controllers\Admin\Approval;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class MasterDataSubstitutionController extends BaseController
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = DB::table('approval_substitution')
                ->where('approval_substitution_type', '2')
                ->orderByDesc('approval_substitution_from')
                ->orderByDesc('approval_substitution_to')
                ->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('approval_substitution_from', function ($row) {
                    return date('d-m-Y', strtotime($row->approval_substitution_from));
                })
                ->addColumn('approval_substitution_to', function ($row) {
                    return date('d-m-Y', strtotime($row->approval_substitution_to));
                })
                ->addColumn('status', function ($row) {
                    $now = date('Y-m-d');
                    if ($now < $row->approval_substitution_from) {
                        return '<span class="badge badge-info">Scheduled</span>';
                    } elseif ($now >= $row->approval_substitution_from && $now <= $row->approval_substitution_to) {
                        return '<span class="badge badge-success">Active</span>';
                    } else {
                        return '<span class="badge badge-default">Expired</span>';
                    }
                })
                ->addColumn('action', function ($row) {
                    $btn = '<a href="' . route('admin.master-data-substitution.edit', $row->approval_substitution_id) . '" class="btn btn-sm btn-warning"><i class="fa fa-edit"></i></a> ';
                    $btn .= '<button class="btn btn-sm btn-danger btn-delete" data-id="' . $row->approval_substitution_id . '"><i class="fa fa-trash"></i></button>';
                    return $btn;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('admin.approval.master_data_substitution.index');
    }

    public function create()
    {
        // Get all active users for master data substitution
        $users = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'role_code']);

        return view('admin.approval.master_data_substitution.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'approval_substitution_employee_code' => 'required|exists:tc_user,id',
            'approval_substitution_employee_code_target' => 'required|exists:tc_user,id',
            'approval_substitution_from' => 'required|date',
            'approval_substitution_to' => 'required|date|after_or_equal:approval_substitution_from',
        ]);

        // Check system lock
        $systemLocked = DB::table('m_config')->where('config_id', 1)->value('is_lock_system');
        if ($systemLocked === 't') {
            return redirect()->back()->with('error', 'System is locked, please contact manager for more information.')->withInput();
        }

        $sourceId = $validated['approval_substitution_employee_code'];
        $targetId = $validated['approval_substitution_employee_code_target'];

        // Can't substitute with same person
        if ($sourceId == $targetId) {
            return redirect()->back()->with('error', "Substitution employee and substitute can't be the same.")->withInput();
        }

        $source = User::find($sourceId);
        $target = User::find($targetId);

        if (!$source || !$target) {
            return redirect()->back()->with('error', 'Invalid user selection.')->withInput();
        }

        // Check for overlapping substitution periods
        $clash = DB::table('approval_substitution')
            ->where('approval_substitution_employee_code', $sourceId)
            ->where('approval_substitution_employee_code_target', $targetId)
            ->where('approval_substitution_type', '2')
            ->where(function ($q) use ($validated) {
                $q->whereBetween('approval_substitution_from', [$validated['approval_substitution_from'], $validated['approval_substitution_to']])
                    ->orWhereBetween('approval_substitution_to', [$validated['approval_substitution_from'], $validated['approval_substitution_to']])
                    ->orWhere(function ($q2) use ($validated) {
                        $q2->where('approval_substitution_from', '<=', $validated['approval_substitution_from'])
                            ->where('approval_substitution_to', '>=', $validated['approval_substitution_to']);
                    });
            })
            ->exists();

        if ($clash) {
            return redirect()->back()->with('error', 'There is a conflict with existing substitution period for this employee and substitute.')->withInput();
        }

        // Insert substitution record
        DB::table('approval_substitution')->insert([
            'approval_substitution_employee_code' => $sourceId,
            'approval_substitution_employee_name' => $source->name,
            'approval_substitution_employee_code_target' => $targetId,
            'approval_substitution_employee_name_target' => $target->name,
            'approval_substitution_from' => $validated['approval_substitution_from'],
            'approval_substitution_to' => $validated['approval_substitution_to'],
            'approval_substitution_type' => '2',
            'approval_created_by' => Auth::id(),
            'approval_created_time' => now()->format('H:i:s'),
            'approval_created_date' => now()->format('Y-m-d'),
            'approval_updated_by' => Auth::id(),
            'approval_updated_time' => now()->format('H:i:s'),
            'approval_updated_date' => now()->format('Y-m-d'),
        ]);

        return redirect()->route('admin.master-data-substitution.index')->with('success', 'Master data substitution successfully created.');
    }

    public function destroy($id)
    {
        // Check system lock
        $systemLocked = DB::table('m_config')->where('config_id', 1)->value('is_lock_system');
        if ($systemLocked === 't') {
            return response()->json(['success' => false, 'message' => 'System is locked, please contact manager for more information.']);
        }

        $deleted = DB::table('approval_substitution')
            ->where('approval_substitution_id', $id)
            ->where('approval_substitution_type', '2')
            ->delete();

        if ($deleted) {
            return response()->json(['success' => true, 'message' => 'Master data substitution deleted successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to delete substitution.']);
    }
}
