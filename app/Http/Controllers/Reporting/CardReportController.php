<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CardReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.card.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        // Card Report - Employee card/attendance card tracking
        $query = DB::table('m_employee')
            ->leftJoin('t_attendance', function($join) use ($dateRange) {
                $join->on('m_employee.employee_code', '=', 't_attendance.attendance_employee_code')
                     ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
            })
            ->leftJoin('m_attendance', 't_attendance.attendance_code', '=', 'm_attendance.attendance_code');
        
        if ($divisionCode !== 'ALL') {
            $query->where('m_employee.employee_division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 'm_employee');
        
        $query->select(
            'm_employee.employee_code',
            'm_employee.employee_name',
            'm_employee.employee_division_code',
            'm_employee.employee_status',
            DB::raw('COUNT(t_attendance.attendance_id) as total_attendance_records'),
            DB::raw('COUNT(DISTINCT t_attendance.attendance_date) as unique_days')
        )
        ->groupBy(
            'm_employee.employee_code',
            'm_employee.employee_name',
            'm_employee.employee_division_code',
            'm_employee.employee_status'
        )
        ->orderBy('m_employee.employee_code');
        
        return DataTables::of($query)->addIndexColumn()->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('m_employee')
            ->leftJoin('t_attendance', function($join) use ($dateRange) {
                $join->on('m_employee.employee_code', '=', 't_attendance.attendance_employee_code')
                     ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
            });
        
        if ($divisionCode !== 'ALL') $query->where('m_employee.employee_division_code', $divisionCode);
        $query = $this->applyRoleFilters($query, 'm_employee');
        
        $data = $query->select(
            'm_employee.employee_code',
            'm_employee.employee_name',
            'm_employee.employee_division_code',
            'm_employee.employee_status',
            DB::raw('COUNT(t_attendance.attendance_id) as total_attendance_records'),
            DB::raw('COUNT(DISTINCT t_attendance.attendance_date) as unique_days')
        )
        ->groupBy('m_employee.employee_code', 'm_employee.employee_name', 'm_employee.employee_division_code', 'm_employee.employee_status')
        ->orderBy('m_employee.employee_code')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [$row->employee_code, $row->employee_name, $row->employee_division_code ?? '', $row->employee_status ?? '', $row->total_attendance_records ?? 0, $row->unique_days ?? 0];
        }
        
        $headers = ['Employee Code', 'Employee Name', 'Division', 'Status', 'Total Records', 'Unique Days'];
        return $this->exportToCsv($rows, $headers, 'card_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
