<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CpReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        
        $filters = $this->getStandardFilters($request);
        
        // Add ramp/receiving point options
        $filters['ramps'] = DB::table('m_receiving_point')
            ->select('receiving_point_code', 'receiving_point_name')
            ->orderBy('receiving_point_code')
            ->get();
        
        return view('reporting.transaction.cp.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $rampCode = $request->get('ramp', 'ALL');
        
        // CP Report - Collection Point transactions with loaders
        $query = DB::table('t_cp')
            ->leftJoin('t_cp_loader', function($join) {
                $join->on('t_cp.cp_id', '=', 't_cp_loader.cp_id')
                     ->where('t_cp_loader.cp_loader_type', 1); // Driver type
            })
            ->leftJoin('m_vra', function($join) {
                $join->on(DB::raw('TRIM(m_vra.vra_license_number)'), '=', DB::raw('TRIM(t_cp.cp_license_number)'));
            })
            ->whereBetween(DB::raw('DATE(t_cp.cp_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_cp.cp_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_cp.cp_division_code', $divisionCode);
        }
        
        if ($rampCode !== 'ALL') {
            $query->where('t_cp.cp_receiving_point_code', $rampCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_cp');
        
        $query->select(
            't_cp.cp_id',
            DB::raw('DATE(t_cp.cp_created_date) as cp_date'),
            't_cp.cp_division_code',
            't_cp.cp_license_number',
            't_cp.cp_receiving_point_code',
            't_cp.cp_kerani_kirim_employee_code',
            't_cp.cp_kerani_kirim_employee_name',
            't_cp.cp_type',
            't_cp.cp_bruto',
            't_cp.cp_tarra',
            't_cp.cp_netto',
            't_cp_loader.cp_loader_employee_code',
            't_cp_loader.cp_loader_employee_name',
            't_cp_loader.cp_loader_percentage',
            'm_vra.vra_order_number'
        )
        ->orderBy('cp_date', 'desc')
        ->orderBy('t_cp.cp_id');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('cp_date', fn($row) => date('d-m-Y', strtotime($row->cp_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $rampCode = $request->get('ramp', 'ALL');
        
        $query = DB::table('t_cp')
            ->leftJoin('t_cp_loader', function($join) {
                $join->on('t_cp.cp_id', '=', 't_cp_loader.cp_id')
                     ->where('t_cp_loader.cp_loader_type', 1);
            })
            ->whereBetween(DB::raw('DATE(t_cp.cp_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_cp.cp_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') $query->where('t_cp.cp_division_code', $divisionCode);
        if ($rampCode !== 'ALL') $query->where('t_cp.cp_receiving_point_code', $rampCode);
        
        $query = $this->applyRoleFilters($query, 't_cp');
        
        $data = $query->select(
            DB::raw('DATE(t_cp.cp_created_date) as cp_date'),
            't_cp.cp_division_code',
            't_cp.cp_license_number',
            't_cp.cp_receiving_point_code',
            't_cp.cp_kerani_kirim_employee_name',
            't_cp.cp_type',
            't_cp.cp_bruto',
            't_cp.cp_tarra',
            't_cp.cp_netto',
            't_cp_loader.cp_loader_employee_name',
            't_cp_loader.cp_loader_percentage'
        )
        ->orderBy('cp_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->cp_date)),
                $row->cp_division_code,
                $row->cp_license_number,
                $row->cp_receiving_point_code ?? '',
                $row->cp_kerani_kirim_employee_name ?? '',
                $row->cp_type ?? '',
                $row->cp_bruto ?? 0,
                $row->cp_tarra ?? 0,
                $row->cp_netto ?? 0,
                $row->cp_loader_employee_name ?? '',
                $row->cp_loader_percentage ?? 0,
            ];
        }
        
        $headers = ['Date', 'Division', 'License Number', 'Ramp', 'Transport Clerk', 'CP Type', 'Bruto', 'Tarra', 'Netto', 'Loader', 'Percentage'];
        return $this->exportToCsv($rows, $headers, 'cp_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
