<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SupervisorReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.supervisor.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        // Supervisor Report - Supervisor/Mandor performance tracking
        $query = DB::table('t_user_assignment')
            ->leftJoin('t_oph', function($join) use ($dateRange) {
                $join->on('t_user_assignment.mandor_employee_code', '=', 't_oph.mandor_employee_code')
                     ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']]);
            })
            ->leftJoin('t_attendance', function($join) use ($dateRange) {
                $join->on('t_user_assignment.mandor_employee_code', '=', 't_attendance.attendance_employee_code')
                     ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
            });
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_user_assignment.division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_user_assignment');
        
        $query->select(
            't_user_assignment.mandor_employee_code',
            't_user_assignment.mandor_employee_name',
            't_user_assignment.division_code',
            DB::raw('COUNT(DISTINCT t_oph.oph_id) as total_oph'),
            DB::raw('SUM(t_oph.bunches_total) as total_bunches'),
            DB::raw('COUNT(DISTINCT t_attendance.attendance_id) as total_attendance')
        )
        ->groupBy(
            't_user_assignment.mandor_employee_code',
            't_user_assignment.mandor_employee_name',
            't_user_assignment.division_code'
        )
        ->orderByDesc('total_oph');
        
        return DataTables::of($query)->addIndexColumn()->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_user_assignment')
            ->leftJoin('t_oph', function($join) use ($dateRange) {
                $join->on('t_user_assignment.mandor_employee_code', '=', 't_oph.mandor_employee_code')
                     ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']]);
            })
            ->leftJoin('t_attendance', function($join) use ($dateRange) {
                $join->on('t_user_assignment.mandor_employee_code', '=', 't_attendance.attendance_employee_code')
                     ->whereBetween('t_attendance.attendance_date', [$dateRange['from'], $dateRange['to']]);
            });
        
        if ($divisionCode !== 'ALL') $query->where('t_user_assignment.division_code', $divisionCode);
        $query = $this->applyRoleFilters($query, 't_user_assignment');
        
        $data = $query->select(
            't_user_assignment.mandor_employee_code',
            't_user_assignment.mandor_employee_name',
            't_user_assignment.division_code',
            DB::raw('COUNT(DISTINCT t_oph.oph_id) as total_oph'),
            DB::raw('SUM(t_oph.bunches_total) as total_bunches'),
            DB::raw('COUNT(DISTINCT t_attendance.attendance_id) as total_attendance')
        )
        ->groupBy('t_user_assignment.mandor_employee_code', 't_user_assignment.mandor_employee_name', 't_user_assignment.division_code')
        ->orderByDesc('total_oph')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [$row->mandor_employee_code, $row->mandor_employee_name, $row->division_code ?? '', $row->total_oph ?? 0, $row->total_bunches ?? 0, $row->total_attendance ?? 0];
        }
        
        $headers = ['Supervisor Code', 'Supervisor Name', 'Division', 'Total OPH', 'Total Bunches', 'Total Attendance'];
        return $this->exportToCsv($rows, $headers, 'supervisor_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
