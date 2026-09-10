<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class OphSummaryReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) {
            return $this->getDatatable($request);
        }
        return view('reporting.transaction.oph_summary.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_oph')
            ->leftJoin('m_employee', 't_oph.harvester_employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_activity', 't_oph.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween(DB::raw('DATE(t_oph.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_oph.division_code', $divisionCode);
        
        $query = $this->applyRoleFilters($query, 't_oph');
        
        // Aggregate by harvester and activity
        $query->select(
            't_oph.harvester_employee_code',
            'm_employee.employee_name',
            't_oph.activity_code',
            'm_activity.activity_name',
            DB::raw('SUM(t_oph.bunches_total) as total_bunches'),
            DB::raw('COUNT(t_oph.oph_id) as trip_count')
        )
        ->groupBy('t_oph.harvester_employee_code', 'm_employee.employee_name', 't_oph.activity_code', 'm_activity.activity_name');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_oph')
            ->leftJoin('m_employee', 't_oph.harvester_employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_activity', 't_oph.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween(DB::raw('DATE(t_oph.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_oph.division_code', $divisionCode);
        $query = $this->applyRoleFilters($query, 't_oph');
        
        $data = $query->select(
            't_oph.harvester_employee_code',
            'm_employee.employee_name',
            't_oph.activity_code',
            'm_activity.activity_name',
            DB::raw('SUM(t_oph.bunches_total) as total_bunches'),
            DB::raw('COUNT(t_oph.oph_id) as trip_count')
        )
        ->groupBy('t_oph.harvester_employee_code', 'm_employee.employee_name', 't_oph.activity_code', 'm_activity.activity_name')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [$row->harvester_employee_code, $row->employee_name, $row->activity_code, $row->activity_name, $row->total_bunches, $row->trip_count];
        }
        
        $headers = ['Harvester Code', 'Harvester Name', 'Activity Code', 'Activity', 'Total Bunches', 'Trip Count'];
        return $this->exportToCsv($rows, $headers, 'oph_summary_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
