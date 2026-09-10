<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class GiRReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        
        $filters = $this->getStandardFilters($request);
        
        // Add work type options
        $filters['worktypes'] = DB::table('m_worktype')
            ->select('worktype_code', 'worktype_name')
            ->orderBy('worktype_code')
            ->get();
        
        return view('reporting.transaction.gi_r.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $worktypeCode = $request->get('worktype', 'ALL');
        
        // GI-R Report - Goods Issue Reversal transactions
        $query = DB::table('t_gi_r')
            ->leftJoin('m_worktype', 't_gi_r.gi_r_wtype', '=', 'm_worktype.worktype_code')
            ->leftJoin('m_block', 't_gi_r.gi_r_block', '=', 'm_block.block_code')
            ->whereBetween(DB::raw('DATE(t_gi_r.gi_r_posting_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_gi_r.gi_r_division_code', $divisionCode);
        }
        
        if ($worktypeCode !== 'ALL') {
            $query->where('t_gi_r.gi_r_wtype', $worktypeCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_gi_r');
        
        $query->select(
            't_gi_r.gi_r_id',
            DB::raw('DATE(t_gi_r.gi_r_posting_date) as posting_date'),
            't_gi_r.gi_r_division_code',
            't_gi_r.gi_r_mvt',
            't_gi_r.gi_r_block',
            'm_block.block_name',
            't_gi_r.gi_r_wtype',
            'm_worktype.worktype_name',
            't_gi_r.gi_r_material',
            't_gi_r.gi_r_mat_doc',
            't_gi_r.gi_r_quantity',
            't_gi_r.gi_r_uom',
            't_gi_r.gi_r_sloc_code',
            't_gi_r.gi_r_cc_code'
        )
        ->orderBy('posting_date', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('posting_date', fn($row) => date('d-m-Y', strtotime($row->posting_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $worktypeCode = $request->get('worktype', 'ALL');
        
        $query = DB::table('t_gi_r')
            ->leftJoin('m_worktype', 't_gi_r.gi_r_wtype', '=', 'm_worktype.worktype_code')
            ->leftJoin('m_block', 't_gi_r.gi_r_block', '=', 'm_block.block_code')
            ->whereBetween(DB::raw('DATE(t_gi_r.gi_r_posting_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_gi_r.gi_r_division_code', $divisionCode);
        if ($worktypeCode !== 'ALL') $query->where('t_gi_r.gi_r_wtype', $worktypeCode);
        
        $query = $this->applyRoleFilters($query, 't_gi_r');
        
        $data = $query->select(
            DB::raw('DATE(t_gi_r.gi_r_posting_date) as posting_date'),
            't_gi_r.gi_r_division_code',
            't_gi_r.gi_r_mvt',
            't_gi_r.gi_r_block',
            'm_block.block_name',
            't_gi_r.gi_r_wtype',
            'm_worktype.worktype_name',
            't_gi_r.gi_r_material',
            't_gi_r.gi_r_mat_doc',
            't_gi_r.gi_r_quantity',
            't_gi_r.gi_r_uom'
        )
        ->orderBy('posting_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->posting_date)),
                $row->gi_r_division_code,
                $row->gi_r_mvt,
                $row->gi_r_block ?? '',
                $row->block_name ?? '',
                $row->gi_r_wtype ?? '',
                $row->worktype_name ?? '',
                $row->gi_r_material ?? '',
                $row->gi_r_mat_doc ?? '',
                $row->gi_r_quantity ?? 0,
                $row->gi_r_uom ?? '',
            ];
        }
        
        $headers = ['Posting Date', 'Division', 'Movement Type', 'Block', 'Block Name', 'Work Type', 'Work Type Name', 'Material', 'Material Doc', 'Quantity', 'UOM'];
        return $this->exportToCsv($rows, $headers, 'gi_r_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
