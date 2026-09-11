<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PlatformCheckingReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.platform_checking.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');

        $query = DB::table('t_platform_checking')
            ->leftJoin('t_platform_checking_detail', 't_platform_checking.id', '=', 't_platform_checking_detail.platform_checking_id')
            ->leftJoin('m_block', 't_platform_checking.block_code', '=', 'm_block.block_code')
            ->whereBetween('t_platform_checking.check_date', [$dateRange['from'], $dateRange['to']]);

        if ($divisionCode !== 'ALL') {
            $query->where('t_platform_checking.division_code', $divisionCode);
        }

        if ($blockCode !== 'ALL') {
            $query->where('t_platform_checking.block_code', $blockCode);
        }

        $query = $this->applyRoleFilters($query, 't_platform_checking');

        $query->select(
            't_platform_checking.id',
            't_platform_checking.check_date',
            't_platform_checking.division_code',
            't_platform_checking.block_code',
            'm_block.block_name',
            't_platform_checking.tph_code',
            't_platform_checking.check_status',
            't_platform_checking.notes',
            't_platform_checking_detail.detail_type',
            't_platform_checking_detail.detail_value'
        )->orderBy('t_platform_checking.check_date', 'desc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('check_date', fn($row) => date('d-m-Y', strtotime($row->check_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');

        $query = DB::table('t_platform_checking')
            ->leftJoin('t_platform_checking_detail', 't_platform_checking.id', '=', 't_platform_checking_detail.platform_checking_id')
            ->leftJoin('m_block', 't_platform_checking.block_code', '=', 'm_block.block_code')
            ->whereBetween('t_platform_checking.check_date', [$dateRange['from'], $dateRange['to']]);

        if ($divisionCode !== 'ALL') $query->where('t_platform_checking.division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_platform_checking.block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_platform_checking');

        $data = $query->select(
            't_platform_checking.check_date',
            't_platform_checking.division_code',
            't_platform_checking.block_code',
            'm_block.block_name',
            't_platform_checking.tph_code',
            't_platform_checking.check_status',
            't_platform_checking.notes',
            't_platform_checking_detail.detail_type',
            't_platform_checking_detail.detail_value'
        )->orderBy('t_platform_checking.check_date', 'desc')->get();

        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->check_date)),
                $row->division_code ?? '',
                $row->block_code ?? '',
                $row->block_name ?? '',
                $row->tph_code ?? '',
                $row->check_status ?? '',
                $row->notes ?? '',
                $row->detail_type ?? '',
                $row->detail_value ?? ''
            ];
        }

        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'TPH', 'Status', 'Notes', 'Detail Type', 'Detail Value'];
        return $this->exportToCsv($rows, $headers, 'platform_checking_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
