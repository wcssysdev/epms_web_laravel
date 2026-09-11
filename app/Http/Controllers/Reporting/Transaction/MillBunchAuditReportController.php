<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class MillBunchAuditReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.mill_bunch_audit.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');

        $query = DB::table('t_mill_grader_oph')
            ->leftJoin('m_block', 't_mill_grader_oph.block_code', '=', 'm_block.block_code')
            ->whereBetween('t_mill_grader_oph.created_at', [$dateRange['from'] . ' 00:00:00', $dateRange['to'] . ' 23:59:59']);

        if ($divisionCode !== 'ALL') {
            $query->where('t_mill_grader_oph.division_code', $divisionCode);
        }

        if ($blockCode !== 'ALL') {
            $query->where('t_mill_grader_oph.block_code', $blockCode);
        }

        $query = $this->applyRoleFilters($query, 't_mill_grader_oph');

        $query->select(
            't_mill_grader_oph.id',
            't_mill_grader_oph.created_at',
            't_mill_grader_oph.division_code',
            't_mill_grader_oph.block_code',
            'm_block.block_name',
            't_mill_grader_oph.tph_code',
            't_mill_grader_oph.grader_employee_code',
            't_mill_grader_oph.grader_employee_name',
            't_mill_grader_oph.integration_status',
            't_mill_grader_oph.is_approved',
            't_mill_grader_oph.remark'
        )->orderBy('t_mill_grader_oph.created_at', 'desc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('created_at', fn($row) => date('d-m-Y', strtotime($row->created_at)))
            ->editColumn('is_approved', fn($row) => $row->is_approved ? 'Approved' : 'Pending')
            ->editColumn('integration_status', fn($row) => match((int)$row->integration_status) {
                2 => 'Sent to SAP',
                5 => 'Adjustment',
                default => 'Pending / Draft'
            })
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');

        $query = DB::table('t_mill_grader_oph')
            ->leftJoin('m_block', 't_mill_grader_oph.block_code', '=', 'm_block.block_code')
            ->whereBetween('t_mill_grader_oph.created_at', [$dateRange['from'] . ' 00:00:00', $dateRange['to'] . ' 23:59:59']);

        if ($divisionCode !== 'ALL') $query->where('t_mill_grader_oph.division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_mill_grader_oph.block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_mill_grader_oph');

        $data = $query->select(
            't_mill_grader_oph.created_at',
            't_mill_grader_oph.division_code',
            't_mill_grader_oph.block_code',
            'm_block.block_name',
            't_mill_grader_oph.tph_code',
            't_mill_grader_oph.grader_employee_code',
            't_mill_grader_oph.grader_employee_name',
            't_mill_grader_oph.integration_status',
            't_mill_grader_oph.is_approved',
            't_mill_grader_oph.remark'
        )->orderBy('t_mill_grader_oph.created_at', 'desc')->get();

        $rows = [];
        foreach ($data as $row) {
            $statusLabel = match((int)$row->integration_status) {
                2 => 'Sent to SAP',
                5 => 'Adjustment',
                default => 'Pending / Draft'
            };
            $rows[] = [
                date('d-m-Y', strtotime($row->created_at)),
                $row->division_code ?? '',
                $row->block_code ?? '',
                $row->block_name ?? '',
                $row->tph_code ?? '',
                $row->grader_employee_code ?? '',
                $row->grader_employee_name ?? '',
                $statusLabel,
                $row->is_approved ? 'Approved' : 'Pending',
                $row->remark ?? ''
            ];
        }

        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'TPH', 'Grader Code', 'Grader Name', 'SAP Status', 'Approval', 'Remark'];
        return $this->exportToCsv($rows, $headers, 'mill_bunch_audit_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
