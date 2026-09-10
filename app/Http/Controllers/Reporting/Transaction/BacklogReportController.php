<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BacklogReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.backlog.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        // Backlog Report - Pending tasks and workplan backlog
        $query = DB::table('t_workplan')
            ->leftJoin('m_activity', 't_workplan.activity_code', '=', 'm_activity.activity_code')
            ->leftJoin('m_block', 't_workplan.workplan_block_code', '=', 'm_block.block_code')
            ->leftJoin('t_workdone', function($join) {
                $join->on('t_workplan.workplan_id', '=', 't_workdone.workplan_id');
            })
            ->whereBetween('t_workplan.workplan_date', [$dateRange['from'], $dateRange['to']])
            ->where(function($q) {
                $q->whereNull('t_workdone.workdone_id')
                  ->orWhereRaw('t_workplan.workplan_target > COALESCE(t_workdone.workdone_quantity, 0)');
            });
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_workplan.workplan_division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_workplan.workplan_block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_workplan');
        
        $query->select(
            't_workplan.workplan_date',
            't_workplan.workplan_division_code',
            't_workplan.workplan_block_code',
            'm_block.block_name',
            't_workplan.activity_code',
            'm_activity.activity_name',
            't_workplan.workplan_target',
            DB::raw('COALESCE(SUM(t_workdone.workdone_quantity), 0) as completed_quantity'),
            DB::raw('t_workplan.workplan_target - COALESCE(SUM(t_workdone.workdone_quantity), 0) as backlog_quantity'),
            DB::raw('ROUND((COALESCE(SUM(t_workdone.workdone_quantity), 0) / NULLIF(t_workplan.workplan_target, 0)) * 100, 2) as completion_percentage')
        )
        ->groupBy(
            't_workplan.workplan_id',
            't_workplan.workplan_date',
            't_workplan.workplan_division_code',
            't_workplan.workplan_block_code',
            'm_block.block_name',
            't_workplan.activity_code',
            'm_activity.activity_name',
            't_workplan.workplan_target'
        )
        ->orderBy('t_workplan.workplan_date', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('workplan_date', fn($row) => date('d-m-Y', strtotime($row->workplan_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        $query = DB::table('t_workplan')
            ->leftJoin('m_activity', 't_workplan.activity_code', '=', 'm_activity.activity_code')
            ->leftJoin('m_block', 't_workplan.workplan_block_code', '=', 'm_block.block_code')
            ->leftJoin('t_workdone', function($join) {
                $join->on('t_workplan.workplan_id', '=', 't_workdone.workplan_id');
            })
            ->whereBetween('t_workplan.workplan_date', [$dateRange['from'], $dateRange['to']])
            ->where(function($q) {
                $q->whereNull('t_workdone.workdone_id')
                  ->orWhereRaw('t_workplan.workplan_target > COALESCE(t_workdone.workdone_quantity, 0)');
            });
        
        if ($divisionCode !== 'ALL') $query->where('t_workplan.workplan_division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_workplan.workplan_block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_workplan');
        
        $data = $query->select(
            't_workplan.workplan_date',
            't_workplan.workplan_division_code',
            't_workplan.workplan_block_code',
            'm_block.block_name',
            't_workplan.activity_code',
            'm_activity.activity_name',
            't_workplan.workplan_target',
            DB::raw('COALESCE(SUM(t_workdone.workdone_quantity), 0) as completed_quantity'),
            DB::raw('t_workplan.workplan_target - COALESCE(SUM(t_workdone.workdone_quantity), 0) as backlog_quantity'),
            DB::raw('ROUND((COALESCE(SUM(t_workdone.workdone_quantity), 0) / NULLIF(t_workplan.workplan_target, 0)) * 100, 2) as completion_percentage')
        )
        ->groupBy('t_workplan.workplan_id', 't_workplan.workplan_date', 't_workplan.workplan_division_code', 't_workplan.workplan_block_code', 'm_block.block_name', 't_workplan.activity_code', 'm_activity.activity_name', 't_workplan.workplan_target')
        ->orderBy('t_workplan.workplan_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->workplan_date)),
                $row->workplan_division_code ?? '',
                $row->workplan_block_code ?? '',
                $row->block_name ?? '',
                $row->activity_code ?? '',
                $row->activity_name ?? '',
                $row->workplan_target ?? 0,
                $row->completed_quantity ?? 0,
                $row->backlog_quantity ?? 0,
                $row->completion_percentage ?? 0
            ];
        }
        
        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Activity Code', 'Activity Name', 'Target', 'Completed', 'Backlog', 'Completion %'];
        return $this->exportToCsv($rows, $headers, 'backlog_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
