<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class TaskHarvesterReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.task_harvester.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        // Task Harvester Report - Harvesting tasks by employee and activity
        $query = DB::table('t_oph')
            ->leftJoin('t_oph_persons', 't_oph.oph_id', '=', 't_oph_persons.oph_id')
            ->leftJoin('m_activity', 't_oph.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_oph.oph_division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_oph.oph_block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_oph');
        
        $query->select(
            DB::raw('DATE(t_oph.oph_created_date) as oph_date'),
            't_oph.oph_division_code',
            't_oph.oph_block_code',
            't_oph.activity_code',
            'm_activity.activity_name',
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            't_oph_persons.oph_person_type',
            DB::raw('COUNT(t_oph.oph_id) as task_count'),
            DB::raw('SUM(t_oph.bunches_total) as total_bunches')
        )
        ->groupBy(
            'oph_date',
            't_oph.oph_division_code',
            't_oph.oph_block_code',
            't_oph.activity_code',
            'm_activity.activity_name',
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            't_oph_persons.oph_person_type'
        )
        ->orderBy('oph_date')
        ->orderByDesc('task_count');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('oph_date', fn($row) => date('d-m-Y', strtotime($row->oph_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        $query = DB::table('t_oph')
            ->leftJoin('t_oph_persons', 't_oph.oph_id', '=', 't_oph_persons.oph_id')
            ->leftJoin('m_activity', 't_oph.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_oph.oph_division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_oph.oph_block_code', $blockCode);
        
        $query = $this->applyRoleFilters($query, 't_oph');
        
        $data = $query->select(
            DB::raw('DATE(t_oph.oph_created_date) as oph_date'),
            't_oph.oph_division_code',
            't_oph.oph_block_code',
            't_oph.activity_code',
            'm_activity.activity_name',
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            't_oph_persons.oph_person_type',
            DB::raw('COUNT(t_oph.oph_id) as task_count'),
            DB::raw('SUM(t_oph.bunches_total) as total_bunches')
        )
        ->groupBy(
            'oph_date',
            't_oph.oph_division_code',
            't_oph.oph_block_code',
            't_oph.activity_code',
            'm_activity.activity_name',
            't_oph_persons.oph_person_employee_code',
            't_oph_persons.oph_person_employee_name',
            't_oph_persons.oph_person_type'
        )
        ->orderBy('oph_date')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->oph_date)),
                $row->oph_division_code,
                $row->oph_block_code,
                $row->activity_code ?? '',
                $row->activity_name ?? '',
                $row->oph_person_employee_code ?? '',
                $row->oph_person_employee_name ?? '',
                $row->oph_person_type ?? '',
                $row->task_count ?? 0,
                $row->total_bunches ?? 0,
            ];
        }
        
        $headers = ['Date', 'Division', 'Block', 'Activity Code', 'Activity', 'Employee Code', 'Employee Name', 'Person Type', 'Task Count', 'Total Bunches'];
        return $this->exportToCsv($rows, $headers, 'task_harvester_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
