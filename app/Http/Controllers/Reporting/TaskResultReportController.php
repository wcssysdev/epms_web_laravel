<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class TaskResultReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.task_result.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        // Task Result Report - Task completion and results tracking
        $query = DB::table('t_workdone')
            ->leftJoin('m_activity', 't_workdone.activity_code', '=', 'm_activity.activity_code')
            ->leftJoin('m_employee', 't_workdone.workdone_employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_block', 't_workdone.workdone_block_code', '=', 'm_block.block_code')
            ->whereBetween('t_workdone.workdone_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_workdone.workdone_division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_workdone.workdone_block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_workdone');
        
        $query->select(
            't_workdone.workdone_date',
            't_workdone.workdone_division_code',
            't_workdone.workdone_block_code',
            'm_block.block_name',
            't_workdone.activity_code',
            'm_activity.activity_name',
            't_workdone.workdone_employee_code',
            'm_employee.employee_name',
            DB::raw('SUM(t_workdone.workdone_quantity) as total_quantity'),
            DB::raw('SUM(t_workdone.workdone_value) as total_value'),
            DB::raw('COUNT(*) as task_count')
        )
        ->groupBy(
            't_workdone.workdone_date',
            't_workdone.workdone_division_code',
            't_workdone.workdone_block_code',
            'm_block.block_name',
            't_workdone.activity_code',
            'm_activity.activity_name',
            't_workdone.workdone_employee_code',
            'm_employee.employee_name'
        )
        ->orderBy('t_workdone.workdone_date', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('workdone_date', fn($row) => date('d-m-Y', strtotime($row->workdone_date)))
            ->editColumn('total_value', fn($row) => number_format($row->total_value, 2))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        $query = DB::table('t_workdone')
            ->leftJoin('m_activity', 't_workdone.activity_code', '=', 'm_activity.activity_code')
            ->leftJoin('m_employee', 't_workdone.workdone_employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_block', 't_workdone.workdone_block_code', '=', 'm_block.block_code')
            ->whereBetween('t_workdone.workdone_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_workdone.workdone_division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_workdone.workdone_block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_workdone');
        
        $data = $query->select(
            't_workdone.workdone_date',
            't_workdone.workdone_division_code',
            't_workdone.workdone_block_code',
            'm_block.block_name',
            't_workdone.activity_code',
            'm_activity.activity_name',
            't_workdone.workdone_employee_code',
            'm_employee.employee_name',
            DB::raw('SUM(t_workdone.workdone_quantity) as total_quantity'),
            DB::raw('SUM(t_workdone.workdone_value) as total_value'),
            DB::raw('COUNT(*) as task_count')
        )
        ->groupBy('t_workdone.workdone_date', 't_workdone.workdone_division_code', 't_workdone.workdone_block_code', 'm_block.block_name', 't_workdone.activity_code', 'm_activity.activity_name', 't_workdone.workdone_employee_code', 'm_employee.employee_name')
        ->orderBy('t_workdone.workdone_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->workdone_date)),
                $row->workdone_division_code ?? '',
                $row->workdone_block_code ?? '',
                $row->block_name ?? '',
                $row->activity_code ?? '',
                $row->activity_name ?? '',
                $row->workdone_employee_code ?? '',
                $row->employee_name ?? '',
                $row->total_quantity ?? 0,
                number_format($row->total_value ?? 0, 2),
                $row->task_count ?? 0
            ];
        }
        
        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Activity Code', 'Activity Name', 'Employee Code', 'Employee Name', 'Total Quantity', 'Total Value', 'Task Count'];
        return $this->exportToCsv($rows, $headers, 'task_result_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
