<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class GrRReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.gr_r.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        // GR-R Report - Goods Receipt Reversal transactions
        $query = DB::table('t_gr_r')
            ->leftJoin('m_vendor', 't_gr_r.gr_r_vendor', '=', 'm_vendor.vendor_code')
            ->whereBetween(DB::raw('DATE(t_gr_r.gr_r_posting_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_gr_r.gr_r_division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_gr_r');
        
        $query->select(
            't_gr_r.gr_r_id',
            DB::raw('DATE(t_gr_r.gr_r_posting_date) as posting_date'),
            't_gr_r.gr_r_division_code',
            't_gr_r.gr_r_mvt',
            't_gr_r.gr_r_vendor',
            'm_vendor.vendor_name',
            't_gr_r.gr_r_material',
            't_gr_r.gr_r_mat_doc',
            't_gr_r.gr_r_quantity',
            't_gr_r.gr_r_uom',
            't_gr_r.gr_r_po_number',
            't_gr_r.gr_r_sloc_code'
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
        
        $query = DB::table('t_gr_r')
            ->leftJoin('m_vendor', 't_gr_r.gr_r_vendor', '=', 'm_vendor.vendor_code')
            ->whereBetween(DB::raw('DATE(t_gr_r.gr_r_posting_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_gr_r.gr_r_division_code', $divisionCode);
        
        $query = $this->applyRoleFilters($query, 't_gr_r');
        
        $data = $query->select(
            DB::raw('DATE(t_gr_r.gr_r_posting_date) as posting_date'),
            't_gr_r.gr_r_division_code',
            't_gr_r.gr_r_mvt',
            't_gr_r.gr_r_vendor',
            'm_vendor.vendor_name',
            't_gr_r.gr_r_material',
            't_gr_r.gr_r_mat_doc',
            't_gr_r.gr_r_quantity',
            't_gr_r.gr_r_uom',
            't_gr_r.gr_r_po_number'
        )
        ->orderBy('posting_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->posting_date)),
                $row->gr_r_division_code,
                $row->gr_r_mvt,
                $row->gr_r_vendor ?? '',
                $row->vendor_name ?? '',
                $row->gr_r_material ?? '',
                $row->gr_r_mat_doc ?? '',
                $row->gr_r_quantity ?? 0,
                $row->gr_r_uom ?? '',
                $row->gr_r_po_number ?? '',
            ];
        }
        
        $headers = ['Posting Date', 'Division', 'Movement Type', 'Vendor Code', 'Vendor Name', 'Material', 'Material Doc', 'Quantity', 'UOM', 'PO Number'];
        return $this->exportToCsv($rows, $headers, 'gr_r_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
