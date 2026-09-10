<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FdnReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        
        $filters = $this->getStandardFilters($request);
        
        // Add destination options
        $filters['destinations'] = DB::table('m_destination')
            ->select('destination_code', 'destination_name')
            ->orderBy('destination_code')
            ->get();
        
        return view('reporting.transaction.fdn.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $destinationCode = $request->get('destination', 'ALL');
        
        // FDN Report - Foundation/Delivery transactions
        $query = DB::table('t_fdn')
            ->leftJoin('t_fdn_loader', function($join) {
                $join->on('t_fdn.fdn_id', '=', 't_fdn_loader.fdn_id')
                     ->where('t_fdn_loader.fdn_loader_type', 2); // Loader type
            })
            ->whereBetween(DB::raw('DATE(t_fdn.fdn_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_fdn.fdn_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_fdn.fdn_division_code', $divisionCode);
        }
        
        if ($destinationCode !== 'ALL') {
            $query->where('t_fdn.fdn_deliver_to_code', $destinationCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_fdn');
        
        $query->select(
            't_fdn.fdn_id',
            DB::raw('DATE(t_fdn.fdn_created_date) as fdn_date'),
            't_fdn.fdn_division_code',
            't_fdn.fdn_license_number',
            't_fdn.fdn_deliver_to_code',
            't_fdn.fdn_kerani_kirim_employee_code',
            't_fdn.fdn_kerani_kirim_employee_name',
            't_fdn.fdn_vehicle_vendor_code',
            't_fdn.fdn_driver_name',
            't_fdn.fdn_bruto',
            't_fdn.fdn_tarra',
            't_fdn.fdn_actual_tonnage',
            't_fdn_loader.fdn_loader_employee_name'
        )
        ->orderBy('fdn_date', 'desc')
        ->orderBy('t_fdn.fdn_id');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('fdn_date', fn($row) => date('d-m-Y', strtotime($row->fdn_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $destinationCode = $request->get('destination', 'ALL');
        
        $query = DB::table('t_fdn')
            ->leftJoin('t_fdn_loader', function($join) {
                $join->on('t_fdn.fdn_id', '=', 't_fdn_loader.fdn_id')
                     ->where('t_fdn_loader.fdn_loader_type', 2);
            })
            ->whereBetween(DB::raw('DATE(t_fdn.fdn_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_fdn.fdn_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') $query->where('t_fdn.fdn_division_code', $divisionCode);
        if ($destinationCode !== 'ALL') $query->where('t_fdn.fdn_deliver_to_code', $destinationCode);
        
        $query = $this->applyRoleFilters($query, 't_fdn');
        
        $data = $query->select(
            DB::raw('DATE(t_fdn.fdn_created_date) as fdn_date'),
            't_fdn.fdn_division_code',
            't_fdn.fdn_license_number',
            't_fdn.fdn_deliver_to_code',
            't_fdn.fdn_kerani_kirim_employee_name',
            't_fdn.fdn_vehicle_vendor_code',
            't_fdn.fdn_driver_name',
            't_fdn.fdn_bruto',
            't_fdn.fdn_tarra',
            't_fdn.fdn_actual_tonnage',
            't_fdn_loader.fdn_loader_employee_name'
        )
        ->orderBy('fdn_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->fdn_date)),
                $row->fdn_division_code,
                $row->fdn_license_number,
                $row->fdn_deliver_to_code ?? '',
                $row->fdn_kerani_kirim_employee_name ?? '',
                $row->fdn_vehicle_vendor_code ?? '',
                $row->fdn_driver_name ?? '',
                $row->fdn_bruto ?? 0,
                $row->fdn_tarra ?? 0,
                $row->fdn_actual_tonnage ?? 0,
                $row->fdn_loader_employee_name ?? '',
            ];
        }
        
        $headers = ['Date', 'Division', 'License Number', 'Destination', 'Transport Clerk', 'Vendor', 'Driver', 'Bruto', 'Tarra', 'Actual Tonnage', 'Loader'];
        return $this->exportToCsv($rows, $headers, 'fdn_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
