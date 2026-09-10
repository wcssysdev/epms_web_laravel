<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CoconutChitGradingReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.coconut_chit_grading.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        // Coconut OPH with grading details (material breakdown)
        $query = DB::table('t_coconut_oph')
            ->join('t_coconut_oph_detail', 't_coconut_oph.coconut_oph_id', '=', 't_coconut_oph_detail.coconut_oph_id')
            ->leftJoin('m_block', function($join) {
                $join->on('m_block.block_code', '=', 't_coconut_oph.coconut_oph_block_code')
                     ->on('m_block.block_division_code', '=', 't_coconut_oph.coconut_oph_division_code');
            })
            ->whereBetween(DB::raw('DATE(t_coconut_oph.coconut_oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_coconut_oph.coconut_oph_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_coconut_oph.coconut_oph_division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_coconut_oph.coconut_oph_block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_coconut_oph');
        
        $query->select(
            DB::raw('DATE(t_coconut_oph.coconut_oph_created_date) as oph_date'),
            't_coconut_oph.coconut_oph_division_code',
            't_coconut_oph.coconut_oph_block_code',
            'm_block.block_name',
            't_coconut_oph_detail.coconut_oph_detail_material_code',
            't_coconut_oph_detail.coconut_oph_detail_material_name',
            DB::raw('SUM(t_coconut_oph_detail.coconut_oph_detail_quantity) as total_quantity')
        )
        ->groupBy(
            'oph_date',
            't_coconut_oph.coconut_oph_division_code',
            't_coconut_oph.coconut_oph_block_code',
            'm_block.block_name',
            't_coconut_oph_detail.coconut_oph_detail_material_code',
            't_coconut_oph_detail.coconut_oph_detail_material_name'
        )
        ->orderBy('oph_date')
        ->orderBy('t_coconut_oph.coconut_oph_division_code')
        ->orderBy('t_coconut_oph.coconut_oph_block_code');
        
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
        
        $query = DB::table('t_coconut_oph')
            ->join('t_coconut_oph_detail', 't_coconut_oph.coconut_oph_id', '=', 't_coconut_oph_detail.coconut_oph_id')
            ->leftJoin('m_block', function($join) {
                $join->on('m_block.block_code', '=', 't_coconut_oph.coconut_oph_block_code')
                     ->on('m_block.block_division_code', '=', 't_coconut_oph.coconut_oph_division_code');
            })
            ->whereBetween(DB::raw('DATE(t_coconut_oph.coconut_oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_coconut_oph.coconut_oph_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') $query->where('t_coconut_oph.coconut_oph_division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_coconut_oph.coconut_oph_block_code', $blockCode);
        
        $query = $this->applyRoleFilters($query, 't_coconut_oph');
        
        $data = $query->select(
            DB::raw('DATE(t_coconut_oph.coconut_oph_created_date) as oph_date'),
            't_coconut_oph.coconut_oph_division_code',
            't_coconut_oph.coconut_oph_block_code',
            'm_block.block_name',
            't_coconut_oph_detail.coconut_oph_detail_material_code',
            't_coconut_oph_detail.coconut_oph_detail_material_name',
            DB::raw('SUM(t_coconut_oph_detail.coconut_oph_detail_quantity) as total_quantity')
        )
        ->groupBy(
            'oph_date',
            't_coconut_oph.coconut_oph_division_code',
            't_coconut_oph.coconut_oph_block_code',
            'm_block.block_name',
            't_coconut_oph_detail.coconut_oph_detail_material_code',
            't_coconut_oph_detail.coconut_oph_detail_material_name'
        )
        ->orderBy('oph_date')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->oph_date)),
                $row->coconut_oph_division_code,
                $row->coconut_oph_block_code,
                $row->block_name ?? '',
                $row->coconut_oph_detail_material_code,
                $row->coconut_oph_detail_material_name,
                $row->total_quantity,
            ];
        }
        
        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Material Code', 'Material Name', 'Total Quantity'];
        return $this->exportToCsv($rows, $headers, 'coconut_chit_grading_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
