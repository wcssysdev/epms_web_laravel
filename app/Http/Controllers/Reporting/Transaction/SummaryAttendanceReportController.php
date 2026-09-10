<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SummaryAttendanceReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        
        if ($request->ajax()) {
            $reportType = $request->get('report_type', 'EMPLOYEE');
            if ($reportType === 'CATEGORY') {
                return $this->getDatatableByCategory($request);
            }
            return $this->getDatatableByEmployee($request);
        }
        
        $filters = $this->getStandardFilters($request);
        
        // Add attendance types for filter
        $filters['attendance_types'] = DB::table('m_attendance')
            ->select('attendance_code', 'attendance_desc')
            ->get();
        
        return view('reporting.transaction.summary_attendance.index', $filters);
    }

    protected function getDatatableByEmployee(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $attendanceCode = $request->get('attendance_code', 'ALL');
        
        $query = DB::table('t_attendance')
            ->join('m_employee', 't_attendance.attendance_employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_attendance', 't_attendance.attendance_code', '=', 'm_attendance.attendance_code')
            ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_attendance.attendance_division_code', $divisionCode);
        }
        
        if ($attendanceCode !== 'ALL') {
            $query->where('t_attendance.attendance_code', $attendanceCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_attendance');
        
        $query->select(
            't_attendance.attendance_employee_code',
            'm_employee.employee_name',
            't_attendance.attendance_code',
            'm_attendance.attendance_desc',
            DB::raw('COUNT(*) as total_days')
        )
        ->groupBy(
            't_attendance.attendance_employee_code',
            'm_employee.employee_name',
            't_attendance.attendance_code',
            'm_attendance.attendance_desc'
        )
        ->orderBy('t_attendance.attendance_employee_code');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

    protected function getDatatableByCategory(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_attendance')
            ->leftJoin('m_attendance', 't_attendance.attendance_code', '=', 'm_attendance.attendance_code')
            ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_attendance.attendance_division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_attendance');
        
        $query->select(
            't_attendance.attendance_code',
            'm_attendance.attendance_desc',
            DB::raw('COUNT(*) as total_count'),
            DB::raw('COUNT(DISTINCT t_attendance.attendance_employee_code) as unique_employees')
        )
        ->groupBy('t_attendance.attendance_code', 'm_attendance.attendance_desc')
        ->orderBy('t_attendance.attendance_code');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $attendanceCode = $request->get('attendance_code', 'ALL');
        $reportType = $request->get('report_type', 'EMPLOYEE');
        
        if ($reportType === 'CATEGORY') {
            return $this->exportByCategory($request);
        }
        
        $query = DB::table('t_attendance')
            ->join('m_employee', 't_attendance.attendance_employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_attendance', 't_attendance.attendance_code', '=', 'm_attendance.attendance_code')
            ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_attendance.attendance_division_code', $divisionCode);
        if ($attendanceCode !== 'ALL') $query->where('t_attendance.attendance_code', $attendanceCode);
        
        $query = $this->applyRoleFilters($query, 't_attendance');
        
        $data = $query->select(
            't_attendance.attendance_employee_code',
            'm_employee.employee_name',
            't_attendance.attendance_code',
            'm_attendance.attendance_desc',
            DB::raw('COUNT(*) as total_days')
        )
        ->groupBy(
            't_attendance.attendance_employee_code',
            'm_employee.employee_name',
            't_attendance.attendance_code',
            'm_attendance.attendance_desc'
        )
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                $row->attendance_employee_code,
                $row->employee_name,
                $row->attendance_code,
                $row->attendance_desc ?? '',
                $row->total_days,
            ];
        }
        
        $headers = ['Employee Code', 'Employee Name', 'Attendance Code', 'Attendance Type', 'Total Days'];
        return $this->exportToCsv($rows, $headers, 'summary_attendance_by_employee_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }

    protected function exportByCategory(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_attendance')
            ->leftJoin('m_attendance', 't_attendance.attendance_code', '=', 'm_attendance.attendance_code')
            ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_attendance.attendance_division_code', $divisionCode);
        $query = $this->applyRoleFilters($query, 't_attendance');
        
        $data = $query->select(
            't_attendance.attendance_code',
            'm_attendance.attendance_desc',
            DB::raw('COUNT(*) as total_count'),
            DB::raw('COUNT(DISTINCT t_attendance.attendance_employee_code) as unique_employees')
        )
        ->groupBy('t_attendance.attendance_code', 'm_attendance.attendance_desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                $row->attendance_code,
                $row->attendance_desc ?? '',
                $row->total_count,
                $row->unique_employees,
            ];
        }
        
        $headers = ['Attendance Code', 'Attendance Type', 'Total Records', 'Unique Employees'];
        return $this->exportToCsv($rows, $headers, 'summary_attendance_by_category_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
