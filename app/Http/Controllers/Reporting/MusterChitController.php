<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class MusterChitController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.muster_chit.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        // Combined query: workdone + attendance by employee and date
        $query = DB::table('m_employee')
            ->leftJoin('t_attendance', function($join) use ($dateRange) {
                $join->on('m_employee.employee_code', '=', 't_attendance.employee_code')
                     ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
            })
            ->leftJoin('t_workdone', function($join) use ($dateRange) {
                $join->on('m_employee.employee_code', '=', 't_workdone.employee_code')
                     ->whereBetween(DB::raw('DATE(t_workdone.created_at)'), [$dateRange['from'], $dateRange['to']]);
            })
            ->leftJoin('m_activity', 't_workdone.activity_code', '=', 'm_activity.activity_code');
        
        if ($divisionCode !== 'ALL') {
            $query->where(function($q) use ($divisionCode) {
                $q->where('t_attendance.division_code', $divisionCode)
                  ->orWhere('t_workdone.division_code', $divisionCode);
            });
        }
        
        $query->select(
            'm_employee.employee_code',
            'm_employee.employee_name',
            't_attendance.attendance_date',
            't_attendance.attendance_type',
            'm_activity.activity_name',
            DB::raw('SUM(t_workdone.quantity) as total_quantity')
        )
        ->groupBy('m_employee.employee_code', 'm_employee.employee_name', 't_attendance.attendance_date', 't_attendance.attendance_type', 'm_activity.activity_name');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('attendance_date', fn($row) => $row->attendance_date ? date('d-m-Y', strtotime($row->attendance_date)) : '-')
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('m_employee')
            ->leftJoin('t_attendance', function($join) use ($dateRange) {
                $join->on('m_employee.employee_code', '=', 't_attendance.employee_code')
                     ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
            })
            ->leftJoin('t_workdone', function($join) use ($dateRange) {
                $join->on('m_employee.employee_code', '=', 't_workdone.employee_code')
                     ->whereBetween(DB::raw('DATE(t_workdone.created_at)'), [$dateRange['from'], $dateRange['to']]);
            })
            ->leftJoin('m_activity', 't_workdone.activity_code', '=', 'm_activity.activity_code');
        
        if ($divisionCode !== 'ALL') {
            $query->where(function($q) use ($divisionCode) {
                $q->where('t_attendance.division_code', $divisionCode)
                  ->orWhere('t_workdone.division_code', $divisionCode);
            });
        }
        
        $data = $query->select(
            'm_employee.employee_code',
            'm_employee.employee_name',
            't_attendance.attendance_date',
            't_attendance.attendance_type',
            'm_activity.activity_name',
            DB::raw('SUM(t_workdone.quantity) as total_quantity')
        )
        ->groupBy('m_employee.employee_code', 'm_employee.employee_name', 't_attendance.attendance_date', 't_attendance.attendance_type', 'm_activity.activity_name')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                $row->employee_code,
                $row->employee_name,
                $row->attendance_date ? date('d-m-Y', strtotime($row->attendance_date)) : '-',
                $row->attendance_type ?? '-',
                $row->activity_name ?? '-',
                $row->total_quantity ?? 0,
            ];
        }
        
        $headers = ['Employee Code', 'Employee Name', 'Date', 'Attendance Type', 'Activity', 'Total Quantity'];
        return $this->exportToCsv($rows, $headers, 'muster_chit_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
