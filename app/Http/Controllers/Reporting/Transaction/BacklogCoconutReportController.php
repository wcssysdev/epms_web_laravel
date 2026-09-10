<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BacklogCoconutReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.backlog_coconut.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        // Backlog Coconut Report - Coconut harvesting backlog
        $query = DB::table('t_harvesting_plan_coconut')
            ->leftJoin('m_block', 't_harvesting_plan_coconut.block_code', '=', 'm_block.block_code')
            ->leftJoin('t_harvesting_chit_coconut', function($join) {
                $join->on('t_harvesting_plan_coconut.harvesting_plan_coconut_id', '=', 't_harvesting_chit_coconut.harvesting_plan_coconut_id');
            })
            ->whereBetween('t_harvesting_plan_coconut.harvesting_date', [$dateRange['from'], $dateRange['to']])
            ->where(function($q) {
                $q->whereNull('t_harvesting_chit_coconut.harvesting_chit_coconut_id')
                  ->orWhereRaw('t_harvesting_plan_coconut.target_quantity > COALESCE(t_harvesting_chit_coconut.actual_quantity, 0)');
            });
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_harvesting_plan_coconut.division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_harvesting_plan_coconut.block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_harvesting_plan_coconut');
        
        $query->select(
            't_harvesting_plan_coconut.harvesting_date',
            't_harvesting_plan_coconut.division_code',
            't_harvesting_plan_coconut.block_code',
            'm_block.block_name',
            't_harvesting_plan_coconut.target_quantity',
            DB::raw('COALESCE(SUM(t_harvesting_chit_coconut.actual_quantity), 0) as harvested_quantity'),
            DB::raw('t_harvesting_plan_coconut.target_quantity - COALESCE(SUM(t_harvesting_chit_coconut.actual_quantity), 0) as backlog_quantity'),
            DB::raw('ROUND((COALESCE(SUM(t_harvesting_chit_coconut.actual_quantity), 0) / NULLIF(t_harvesting_plan_coconut.target_quantity, 0)) * 100, 2) as completion_percentage')
        )
        ->groupBy(
            't_harvesting_plan_coconut.harvesting_plan_coconut_id',
            't_harvesting_plan_coconut.harvesting_date',
            't_harvesting_plan_coconut.division_code',
            't_harvesting_plan_coconut.block_code',
            'm_block.block_name',
            't_harvesting_plan_coconut.target_quantity'
        )
        ->orderBy('t_harvesting_plan_coconut.harvesting_date', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('harvesting_date', fn($row) => date('d-m-Y', strtotime($row->harvesting_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        $query = DB::table('t_harvesting_plan_coconut')
            ->leftJoin('m_block', 't_harvesting_plan_coconut.block_code', '=', 'm_block.block_code')
            ->leftJoin('t_harvesting_chit_coconut', function($join) {
                $join->on('t_harvesting_plan_coconut.harvesting_plan_coconut_id', '=', 't_harvesting_chit_coconut.harvesting_plan_coconut_id');
            })
            ->whereBetween('t_harvesting_plan_coconut.harvesting_date', [$dateRange['from'], $dateRange['to']])
            ->where(function($q) {
                $q->whereNull('t_harvesting_chit_coconut.harvesting_chit_coconut_id')
                  ->orWhereRaw('t_harvesting_plan_coconut.target_quantity > COALESCE(t_harvesting_chit_coconut.actual_quantity, 0)');
            });
        
        if ($divisionCode !== 'ALL') $query->where('t_harvesting_plan_coconut.division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_harvesting_plan_coconut.block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_harvesting_plan_coconut');
        
        $data = $query->select(
            't_harvesting_plan_coconut.harvesting_date',
            't_harvesting_plan_coconut.division_code',
            't_harvesting_plan_coconut.block_code',
            'm_block.block_name',
            't_harvesting_plan_coconut.target_quantity',
            DB::raw('COALESCE(SUM(t_harvesting_chit_coconut.actual_quantity), 0) as harvested_quantity'),
            DB::raw('t_harvesting_plan_coconut.target_quantity - COALESCE(SUM(t_harvesting_chit_coconut.actual_quantity), 0) as backlog_quantity'),
            DB::raw('ROUND((COALESCE(SUM(t_harvesting_chit_coconut.actual_quantity), 0) / NULLIF(t_harvesting_plan_coconut.target_quantity, 0)) * 100, 2) as completion_percentage')
        )
        ->groupBy('t_harvesting_plan_coconut.harvesting_plan_coconut_id', 't_harvesting_plan_coconut.harvesting_date', 't_harvesting_plan_coconut.division_code', 't_harvesting_plan_coconut.block_code', 'm_block.block_name', 't_harvesting_plan_coconut.target_quantity')
        ->orderBy('t_harvesting_plan_coconut.harvesting_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->harvesting_date)),
                $row->division_code ?? '',
                $row->block_code ?? '',
                $row->block_name ?? '',
                $row->target_quantity ?? 0,
                $row->harvested_quantity ?? 0,
                $row->backlog_quantity ?? 0,
                $row->completion_percentage ?? 0
            ];
        }
        
        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Target', 'Harvested', 'Backlog', 'Completion %'];
        return $this->exportToCsv($rows, $headers, 'backlog_coconut_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
