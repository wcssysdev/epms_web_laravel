<?php

namespace App\Http\Controllers\Grouping;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * User Assignment - Mandor to Employee mapping.
 * 
 * Access: Admin (role 1), Estate Manager (role 2), Assistant Manager (role 3).
 * Purpose: View and manage mandor-employee assignments.
 */
class UserAssignmentController extends BaseController
{
    public function index(Request $request)
    {
        $search = $request->query('search', '');

        $query = DB::table('t_user_assignment')
            ->select('id', 'mandor_employee_code', 'mandor_employee_name', 
                     'worker_employee_code', 'worker_employee_name',
                     'assignment_valid_from', 'assignment_valid_to')
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->when($search, function($q) use ($search) {
                $q->where(function($sq) use ($search) {
                    $sq->where('mandor_employee_code', 'ILIKE', "%{$search}%")
                       ->orWhere('mandor_employee_name', 'ILIKE', "%{$search}%")
                       ->orWhere('worker_employee_code', 'ILIKE', "%{$search}%")
                       ->orWhere('worker_employee_name', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('mandor_employee_name')
            ->orderBy('worker_employee_name')
            ->paginate(50);

        return view('grouping.user_assignment.index', [
            'title' => 'User Assignment',
            'assignments' => $query,
            'search' => $search,
        ]);
    }
}
