<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AttendanceReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        
        if ($request->ajax()) {
            return $this->getDatatable($request);
        }
        
        $filters = $this->getStandardFilters($request);
        
        return view('reporting.transaction.attendance.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_attendance')
            ->leftJoin('m_employee', 't_attendance.employee_code', '=', 'm_employee.employee_code')
            ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_attendance.division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_attendance');
        
        $query->select('t_attendance.*', 'm_employee.employee_name');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('attendance_date', function ($row) {
                return date('d-m-Y', strtotime($row->attendance_date));
            })
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_attendance')
            ->leftJoin('m_employee', 't_attendance.employee_code', '=', 'm_employee.employee_code')
            ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_attendance.division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_attendance');
        
        $data = $query->select('t_attendance.*', 'm_employee.employee_name')->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->attendance_date)),
                $row->division_code,
                $row->employee_code,
                $row->employee_name,
                $row->attendance_type ?? '',
                $row->notes ?? '',
            ];
        }
        
        $headers = ['Date', 'Division', 'Employee Code', 'Employee Name', 'Attendance Type', 'Notes'];
        $filename = 'attendance_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv';
        
        return $this->exportToCsv($rows, $headers, $filename);
    }
}
