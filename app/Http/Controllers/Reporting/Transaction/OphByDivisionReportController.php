<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class OphByDivisionReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.oph_by_division.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        // Aggregated OPH by date, division, and block
        $query = DB::table('t_oph')
            ->leftJoin('m_block', function($join) {
                $join->on('m_block.block_code', '=', 't_oph.oph_block_code')
                     ->on('m_block.block_division_code', '=', 't_oph.oph_division_code');
            })
            ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_oph.oph_is_deleted', 0)
            ->whereNotNull('t_oph.oph_block_code')
            ->whereNotNull('t_oph.oph_division_code');
        
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
            'm_block.block_name',
            DB::raw('SUM(t_oph.bunches_wet) as bunches_wet'),
            DB::raw('SUM(t_oph.bunches_ripe) as bunches_ripe'),
            DB::raw('SUM(t_oph.bunches_overripe) as bunches_overripe'),
            DB::raw('SUM(t_oph.bunches_underripe) as bunches_underripe'),
            DB::raw('SUM(t_oph.bunches_unripe) as bunches_unripe'),
            DB::raw('SUM(t_oph.bunches_rotten) as bunches_rotten'),
            DB::raw('SUM(t_oph.bunches_long_stalk) as bunches_long_stalk'),
            DB::raw('SUM(t_oph.bunches_empty) as bunches_empty'),
            DB::raw('SUM(t_oph.bunches_dirty) as bunches_dirty'),
            DB::raw('SUM(t_oph.bunches_unfresh) as bunches_unfresh'),
            DB::raw('SUM(t_oph.bunches_old) as bunches_old'),
            DB::raw('SUM(t_oph.bunches_pest_damaged_old) as bunches_pest_damaged_old'),
            DB::raw('SUM(t_oph.bunches_pest_damaged_new) as bunches_pest_damaged_new'),
            DB::raw('SUM(t_oph.bunches_diseased) as bunches_diseased'),
            DB::raw('SUM(t_oph.loose_fruits) as loose_fruits'),
            DB::raw('SUM(t_oph.bunches_total) as bunches_total')
        )
        ->groupBy('oph_date', 't_oph.oph_division_code', 't_oph.oph_block_code', 'm_block.block_name')
        ->orderBy('oph_date')
        ->orderBy('t_oph.oph_division_code')
        ->orderBy('t_oph.oph_block_code');
        
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
            ->leftJoin('m_block', function($join) {
                $join->on('m_block.block_code', '=', 't_oph.oph_block_code')
                     ->on('m_block.block_division_code', '=', 't_oph.oph_division_code');
            })
            ->whereBetween(DB::raw('DATE(t_oph.oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_oph.oph_is_deleted', 0)
            ->whereNotNull('t_oph.oph_block_code')
            ->whereNotNull('t_oph.oph_division_code');
        
        if ($divisionCode !== 'ALL') $query->where('t_oph.oph_division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_oph.oph_block_code', $blockCode);
        
        $query = $this->applyRoleFilters($query, 't_oph');
        
        $data = $query->select(
            DB::raw('DATE(t_oph.oph_created_date) as oph_date'),
            't_oph.oph_division_code',
            't_oph.oph_block_code',
            'm_block.block_name',
            DB::raw('SUM(t_oph.bunches_total) as bunches_total'),
            DB::raw('SUM(t_oph.bunches_ripe) as bunches_ripe'),
            DB::raw('SUM(t_oph.loose_fruits) as loose_fruits')
        )
        ->groupBy('oph_date', 't_oph.oph_division_code', 't_oph.oph_block_code', 'm_block.block_name')
        ->orderBy('oph_date')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->oph_date)),
                $row->oph_division_code,
                $row->oph_block_code,
                $row->block_name ?? '',
                $row->bunches_total,
                $row->bunches_ripe,
                $row->loose_fruits,
            ];
        }
        
        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Total Bunches', 'Ripe Bunches', 'Loose Fruits'];
        return $this->exportToCsv($rows, $headers, 'oph_by_division_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
