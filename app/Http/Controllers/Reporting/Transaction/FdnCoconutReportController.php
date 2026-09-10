<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FdnCoconutReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.fdn_coconut.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        // FDN Coconut - Foundation/delivery coconut records
        $query = DB::table('t_coconut_fdn')
            ->whereBetween(DB::raw('DATE(coconut_fdn_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('coconut_fdn_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') {
            $query->where('coconut_fdn_division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_coconut_fdn');
        
        $query->select(
            'coconut_fdn_id',
            DB::raw('DATE(coconut_fdn_created_date) as fdn_date'),
            'coconut_fdn_division_code',
            'coconut_fdn_license_number',
            'coconut_fdn_vehicle_vendor_code',
            'coconut_fdn_driver_name',
            'coconut_fdn_kerani_kirim_employee_code',
            'coconut_fdn_kerani_kirim_employee_name',
            'coconut_fdn_is_nursery',
            'coconut_fdn_destination',
            'coconut_fdn_sales_order',
            'coconut_fdn_total_oph',
            'coconut_fdn_bruto',
            'coconut_fdn_tarra',
            'coconut_fdn_actual_tonnage'
        )
        ->orderByDesc(DB::raw('DATE(coconut_fdn_created_date)'))
        ->orderByDesc('coconut_fdn_created_time');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('fdn_date', fn($row) => date('d-m-Y', strtotime($row->fdn_date)))
            ->editColumn('coconut_fdn_is_nursery', fn($row) => $row->coconut_fdn_is_nursery ? 'Yes' : 'No')
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_coconut_fdn')
            ->whereBetween(DB::raw('DATE(coconut_fdn_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('coconut_fdn_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') $query->where('coconut_fdn_division_code', $divisionCode);
        
        $query = $this->applyRoleFilters($query, 't_coconut_fdn');
        
        $data = $query->select(
            'coconut_fdn_id',
            DB::raw('DATE(coconut_fdn_created_date) as fdn_date'),
            'coconut_fdn_division_code',
            'coconut_fdn_license_number',
            'coconut_fdn_vehicle_vendor_code',
            'coconut_fdn_driver_name',
            'coconut_fdn_kerani_kirim_employee_code',
            'coconut_fdn_kerani_kirim_employee_name',
            'coconut_fdn_is_nursery',
            'coconut_fdn_destination',
            'coconut_fdn_sales_order',
            'coconut_fdn_total_oph',
            'coconut_fdn_bruto',
            'coconut_fdn_tarra',
            'coconut_fdn_actual_tonnage'
        )
        ->orderByDesc(DB::raw('DATE(coconut_fdn_created_date)'))
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->fdn_date)),
                $row->coconut_fdn_division_code,
                $row->coconut_fdn_license_number,
                $row->coconut_fdn_vehicle_vendor_code ?? '',
                $row->coconut_fdn_driver_name ?? '',
                $row->coconut_fdn_kerani_kirim_employee_code ?? '',
                $row->coconut_fdn_kerani_kirim_employee_name ?? '',
                $row->coconut_fdn_is_nursery ? 'Yes' : 'No',
                $row->coconut_fdn_destination ?? '',
                $row->coconut_fdn_sales_order ?? '',
                $row->coconut_fdn_total_oph ?? 0,
                $row->coconut_fdn_bruto ?? 0,
                $row->coconut_fdn_tarra ?? 0,
                $row->coconut_fdn_actual_tonnage ?? 0,
            ];
        }
        
        $headers = ['Date', 'Division', 'License Number', 'Vendor Code', 'Driver Name', 'Transport Clerk Code', 'Transport Clerk Name', 'Is Nursery', 'Destination', 'Sales Order', 'Total OPH', 'Bruto', 'Tarra', 'Actual Tonnage'];
        return $this->exportToCsv($rows, $headers, 'fdn_coconut_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
