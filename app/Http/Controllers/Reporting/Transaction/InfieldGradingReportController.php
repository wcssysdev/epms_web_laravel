<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class InfieldGradingReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.infield_grading.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');

        $query = DB::table('t_oph')
            ->leftJoin('m_block', 't_oph.block_code', '=', 'm_block.block_code')
            ->whereBetween('t_oph.created_at', [$dateRange['from'] . ' 00:00:00', $dateRange['to'] . ' 23:59:59']);

        if ($divisionCode !== 'ALL') {
            $query->where('t_oph.division_code', $divisionCode);
        }

        if ($blockCode !== 'ALL') {
            $query->where('t_oph.block_code', $blockCode);
        }

        $query = $this->applyRoleFilters($query, 't_oph');

        $query->select(
            't_oph.id',
            't_oph.created_at',
            't_oph.division_code',
            't_oph.block_code',
            'm_block.block_name',
            't_oph.tph_code',
            't_oph.kerani_panen_employee_name',
            't_oph.bunches_ripe',
            't_oph.bunches_unripe',
            't_oph.bunches_overripe',
            't_oph.bunches_underripe',
            't_oph.bunches_rotten',
            't_oph.bunches_empty',
            't_oph.bunches_long_stalk',
            't_oph.bunches_dirty',
            't_oph.loose_fruits',
            't_oph.bunches_total'
        )->orderBy('t_oph.created_at', 'desc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('created_at', fn($row) => date('d-m-Y', strtotime($row->created_at)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');

        $query = DB::table('t_oph')
            ->leftJoin('m_block', 't_oph.block_code', '=', 'm_block.block_code')
            ->whereBetween('t_oph.created_at', [$dateRange['from'] . ' 00:00:00', $dateRange['to'] . ' 23:59:59']);

        if ($divisionCode !== 'ALL') $query->where('t_oph.division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_oph.block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_oph');

        $data = $query->select(
            't_oph.created_at',
            't_oph.division_code',
            't_oph.block_code',
            'm_block.block_name',
            't_oph.tph_code',
            't_oph.kerani_panen_employee_name',
            't_oph.bunches_ripe',
            't_oph.bunches_unripe',
            't_oph.bunches_overripe',
            't_oph.bunches_underripe',
            't_oph.bunches_rotten',
            't_oph.bunches_empty',
            't_oph.bunches_long_stalk',
            't_oph.bunches_dirty',
            't_oph.loose_fruits',
            't_oph.bunches_total'
        )->orderBy('t_oph.created_at', 'desc')->get();

        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->created_at)),
                $row->division_code ?? '',
                $row->block_code ?? '',
                $row->block_name ?? '',
                $row->tph_code ?? '',
                $row->kerani_panen_employee_name ?? '',
                $row->bunches_ripe ?? 0,
                $row->bunches_unripe ?? 0,
                $row->bunches_overripe ?? 0,
                $row->bunches_underripe ?? 0,
                $row->bunches_rotten ?? 0,
                $row->bunches_empty ?? 0,
                $row->bunches_long_stalk ?? 0,
                $row->bunches_dirty ?? 0,
                $row->loose_fruits ?? 0,
                $row->bunches_total ?? 0
            ];
        }

        $headers = [
            'Date', 'Division', 'Block Code', 'Block Name', 'TPH', 'Kerani Panen',
            'Ripe', 'Unripe', 'Overripe', 'Underripe', 'Rotten', 'Empty', 'Long Stalk', 'Dirty', 'Loose Fruits', 'Total Bunches'
        ];
        return $this->exportToCsv($rows, $headers, 'infield_grading_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
