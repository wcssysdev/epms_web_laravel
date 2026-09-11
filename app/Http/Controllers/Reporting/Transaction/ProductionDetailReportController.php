<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ProductionDetailReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.production_detail.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');

        $query = DB::table('t_oph')
            ->leftJoin('m_block', 't_oph.block_code', '=', 'm_block.block_code')
            ->leftJoin('t_fdn_detail', 't_oph.id', '=', 't_fdn_detail.oph_id')
            ->whereBetween('t_oph.created_at', [$dateRange['from'] . ' 00:00:00', $dateRange['to'] . ' 23:59:59']);

        if ($divisionCode !== 'ALL') {
            $query->where('t_oph.division_code', $divisionCode);
        }

        if ($blockCode !== 'ALL') {
            $query->where('t_oph.block_code', $blockCode);
        }

        $query = $this->applyRoleFilters($query, 't_oph');

        $query->select(
            DB::raw('DATE(t_oph.created_at) as prod_date'),
            't_oph.division_code',
            't_oph.block_code',
            'm_block.block_name',
            DB::raw('COALESCE(SUM(t_oph.bunches_total), 0) as total_bunches'),
            DB::raw('COALESCE(SUM(t_oph.loose_fruits), 0) as total_loose_fruits'),
            DB::raw('COALESCE(SUM(t_fdn_detail.bunches_delivered), 0) as bunches_delivered'),
            DB::raw('COALESCE(SUM(t_oph.bunches_not_sent), 0) as bunches_not_sent'),
            DB::raw('ROUND(COALESCE(SUM(t_fdn_detail.loose_fruit_delivered), 0), 2) as loose_fruit_delivered'),
            DB::raw('ROUND((COALESCE(SUM(t_oph.loose_fruits), 0) / NULLIF(SUM(t_oph.bunches_total), 0)) * 100, 2) as loose_fruit_percentage')
        )->groupBy(
            DB::raw('DATE(t_oph.created_at)'),
            't_oph.division_code',
            't_oph.block_code',
            'm_block.block_name'
        )->orderBy(DB::raw('DATE(t_oph.created_at)'), 'desc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('prod_date', fn($row) => date('d-m-Y', strtotime($row->prod_date)))
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
            ->leftJoin('t_fdn_detail', 't_oph.id', '=', 't_fdn_detail.oph_id')
            ->whereBetween('t_oph.created_at', [$dateRange['from'] . ' 00:00:00', $dateRange['to'] . ' 23:59:59']);

        if ($divisionCode !== 'ALL') $query->where('t_oph.division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_oph.block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_oph');

        $data = $query->select(
            DB::raw('DATE(t_oph.created_at) as prod_date'),
            't_oph.division_code',
            't_oph.block_code',
            'm_block.block_name',
            DB::raw('COALESCE(SUM(t_oph.bunches_total), 0) as total_bunches'),
            DB::raw('COALESCE(SUM(t_oph.loose_fruits), 0) as total_loose_fruits'),
            DB::raw('COALESCE(SUM(t_fdn_detail.bunches_delivered), 0) as bunches_delivered'),
            DB::raw('COALESCE(SUM(t_oph.bunches_not_sent), 0) as bunches_not_sent'),
            DB::raw('ROUND(COALESCE(SUM(t_fdn_detail.loose_fruit_delivered), 0), 2) as loose_fruit_delivered'),
            DB::raw('ROUND((COALESCE(SUM(t_oph.loose_fruits), 0) / NULLIF(SUM(t_oph.bunches_total), 0)) * 100, 2) as loose_fruit_percentage')
        )->groupBy(
            DB::raw('DATE(t_oph.created_at)'),
            't_oph.division_code',
            't_oph.block_code',
            'm_block.block_name'
        )->orderBy(DB::raw('DATE(t_oph.created_at)'), 'desc')->get();

        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->prod_date)),
                $row->division_code ?? '',
                $row->block_code ?? '',
                $row->block_name ?? '',
                $row->total_bunches ?? 0,
                $row->total_loose_fruits ?? 0,
                $row->bunches_delivered ?? 0,
                $row->bunches_not_sent ?? 0,
                $row->loose_fruit_delivered ?? 0,
                $row->loose_fruit_percentage ?? 0
            ];
        }

        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Total Bunches', 'Total Loose Fruits', 'Delivered Bunches', 'Not Delivered Bunches', 'Delivered Loose Fruits', 'Loose Fruit %'];
        return $this->exportToCsv($rows, $headers, 'production_detail_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
