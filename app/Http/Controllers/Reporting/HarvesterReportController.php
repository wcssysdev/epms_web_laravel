<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class HarvesterReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        
        $filters = $this->getStandardFilters($request);
        
        // Add mandor/foreman options
        $filters['mandors'] = DB::table('t_user_assignment')
            ->select('mandor_employee_code', 'mandor_employee_name')
            ->distinct()
            ->orderBy('mandor_employee_name')
            ->get();
        
        return view('reporting.harvester.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $mandorCode = $request->get('mandor', 'ALL');
        
        // Harvester Report - Performance by harvester and mandor
        $query = DB::table('t_oph')
            ->leftJoin('t_oph_persons', 't_oph.oph_id', '=', 't_oph_persons.oph_id')
            ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->whereNotNull('t_oph.mandor_employee_code')
            ->whereNotNull('t_oph.oph_division_code');
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_oph.oph_division_code', $divisionCode);
        }
        
        if ($mandorCode !== 'ALL') {
            $query->where('t_oph.mandor_employee_code', $mandorCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_oph');
        
        $query->select(
            't_oph.mandor_employee_code',
            't_oph.mandor_employee_name',
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            DB::raw('COUNT(t_oph_persons.oph_id) as total_oph'),
            DB::raw('SUM(t_oph.loose_fruits) as total_loose_fruits'),
            DB::raw('SUM(t_oph.bunches_total) as total_bunches')
        )
        ->groupBy(
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            't_oph.mandor_employee_code',
            't_oph.mandor_employee_name'
        )
        ->orderByDesc('total_bunches');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $mandorCode = $request->get('mandor', 'ALL');
        
        $query = DB::table('t_oph')
            ->leftJoin('t_oph_persons', 't_oph.oph_id', '=', 't_oph_persons.oph_id')
            ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->whereNotNull('t_oph.mandor_employee_code')
            ->whereNotNull('t_oph.oph_division_code');
        
        if ($divisionCode !== 'ALL') $query->where('t_oph.oph_division_code', $divisionCode);
        if ($mandorCode !== 'ALL') $query->where('t_oph.mandor_employee_code', $mandorCode);
        
        $query = $this->applyRoleFilters($query, 't_oph');
        
        $data = $query->select(
            't_oph.mandor_employee_code',
            't_oph.mandor_employee_name',
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            DB::raw('COUNT(t_oph_persons.oph_id) as total_oph'),
            DB::raw('SUM(t_oph.loose_fruits) as total_loose_fruits'),
            DB::raw('SUM(t_oph.bunches_total) as total_bunches')
        )
        ->groupBy(
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            't_oph.mandor_employee_code',
            't_oph.mandor_employee_name'
        )
        ->orderByDesc('total_bunches')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                $row->mandor_employee_code ?? '',
                $row->mandor_employee_name ?? '',
                $row->oph_person_employee_code ?? '',
                $row->oph_person_employee_name ?? '',
                $row->total_oph ?? 0,
                $row->total_bunches ?? 0,
                $row->total_loose_fruits ?? 0,
            ];
        }
        
        $headers = ['Mandor Code', 'Mandor Name', 'Harvester Code', 'Harvester Name', 'Total OPH', 'Total Bunches', 'Total Loose Fruits'];
        return $this->exportToCsv($rows, $headers, 'harvester_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
