<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class DailyOphReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.daily_oph.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        // Complex query with OPH details and report calculations
        $query = DB::table('t_oph as t')
            ->leftJoin('t_oph_persons as top', 't.oph_id', '=', 'top.oph_id')
            ->leftJoin('m_report_oph as mro', function($join) {
                $join->on('t.oph_estate_code', '=', 'mro.r_oph_estate_code')
                     ->on('t.oph_division_code', '=', 'mro.r_oph_division_code')
                     ->on('t.oph_block_code', '=', 'mro.r_oph_block_code')
                     ->on(DB::raw('DATE(t.oph_created_date)'), '=', DB::raw('DATE(mro.r_oph_period)'));
            })
            ->whereBetween(DB::raw('DATE(t.oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t.oph_is_deleted', 0)
            ->whereNotNull('t.oph_block_code')
            ->whereNotNull('t.oph_division_code');
        
        if ($divisionCode !== 'ALL') {
            $query->where('t.oph_division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t.oph_block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't');
        
        $query->select(
            DB::raw('DATE(t.oph_created_date) as oph_date'),
            't.oph_id',
            't.oph_estate_code',
            't.oph_division_code',
            't.oph_block_code',
            't.oph_tph_code',
            't.bunches_total',
            't.loose_fruits',
            DB::raw('STRING_AGG(CONCAT(top.oph_person_type, \'|\', top.oph_person_employee_code, \'|\', top.oph_person_employee_name), \',\') as employees'),
            'mro.r_oph_basis as basis',
            'mro.r_oph_gandeng as gandeng',
            'mro.r_oph_premi_basis as premi_basis',
            'mro.r_oph_premi_non_basis as premi_non_basis',
            'mro.r_oph_brondolan_rate_1',
            'mro.r_oph_brondolan_rate_2',
            'mro.r_oph_hk_rate'
        )
        ->groupBy(
            'oph_date',
            't.oph_id',
            't.oph_estate_code',
            't.oph_division_code',
            't.oph_block_code',
            't.oph_tph_code',
            't.bunches_total',
            't.loose_fruits',
            'mro.r_oph_basis',
            'mro.r_oph_gandeng',
            'mro.r_oph_premi_basis',
            'mro.r_oph_premi_non_basis',
            'mro.r_oph_brondolan_rate_1',
            'mro.r_oph_brondolan_rate_2',
            'mro.r_oph_hk_rate'
        )
        ->orderBy('oph_date')
        ->orderBy('t.oph_division_code')
        ->orderBy('t.oph_block_code');
        
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
        
        $query = DB::table('t_oph as t')
            ->leftJoin('t_oph_persons as top', 't.oph_id', '=', 'top.oph_id')
            ->leftJoin('m_report_oph as mro', function($join) {
                $join->on('t.oph_estate_code', '=', 'mro.r_oph_estate_code')
                     ->on('t.oph_division_code', '=', 'mro.r_oph_division_code')
                     ->on('t.oph_block_code', '=', 'mro.r_oph_block_code')
                     ->on(DB::raw('DATE(t.oph_created_date)'), '=', DB::raw('DATE(mro.r_oph_period)'));
            })
            ->whereBetween(DB::raw('DATE(t.oph_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t.oph_is_deleted', 0)
            ->whereNotNull('t.oph_block_code')
            ->whereNotNull('t.oph_division_code');
        
        if ($divisionCode !== 'ALL') $query->where('t.oph_division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t.oph_block_code', $blockCode);
        
        $query = $this->applyRoleFilters($query, 't');
        
        $data = $query->select(
            DB::raw('DATE(t.oph_created_date) as oph_date'),
            't.oph_id',
            't.oph_division_code',
            't.oph_block_code',
            't.oph_tph_code',
            't.bunches_total',
            't.loose_fruits',
            DB::raw('STRING_AGG(CONCAT(top.oph_person_type, \'|\', top.oph_person_employee_code, \'|\', top.oph_person_employee_name), \',\') as employees'),
            'mro.r_oph_basis as basis',
            'mro.r_oph_gandeng as gandeng'
        )
        ->groupBy(
            'oph_date', 't.oph_id', 't.oph_division_code', 't.oph_block_code', 
            't.oph_tph_code', 't.bunches_total', 't.loose_fruits',
            'mro.r_oph_basis', 'mro.r_oph_gandeng'
        )
        ->orderBy('oph_date')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->oph_date)),
                $row->oph_division_code,
                $row->oph_block_code,
                $row->oph_tph_code,
                $row->bunches_total,
                $row->loose_fruits,
                $row->basis ?? 0,
                $row->gandeng ?? 0,
                $row->employees ?? '',
            ];
        }
        
        $headers = ['Date', 'Division', 'Block', 'TPH', 'Bunches Total', 'Loose Fruits', 'Basis', 'Gandeng', 'Employees'];
        return $this->exportToCsv($rows, $headers, 'daily_oph_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
