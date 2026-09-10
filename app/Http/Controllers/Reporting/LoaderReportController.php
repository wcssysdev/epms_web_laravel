<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class LoaderReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.loader.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        // Loader Report - CP and FDN loader performance
        $query = DB::table('t_cp')
            ->join('t_cp_loader', 't_cp.cp_id', '=', 't_cp_loader.cp_id')
            ->whereBetween(DB::raw('DATE(t_cp.cp_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_cp.cp_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_cp.cp_division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_cp');
        
        $query->select(
            't_cp_loader.cp_loader_employee_code',
            't_cp_loader.cp_loader_employee_name',
            't_cp_loader.cp_loader_type',
            DB::raw('COUNT(t_cp.cp_id) as total_trips'),
            DB::raw('SUM(t_cp.cp_netto) as total_netto'),
            DB::raw('AVG(t_cp_loader.cp_loader_percentage) as avg_percentage')
        )
        ->groupBy(
            't_cp_loader.cp_loader_employee_code',
            't_cp_loader.cp_loader_employee_name',
            't_cp_loader.cp_loader_type'
        )
        ->orderByDesc('total_trips');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('cp_loader_type', fn($row) => $row->cp_loader_type == 1 ? 'Driver' : 'Loader')
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_cp')
            ->join('t_cp_loader', 't_cp.cp_id', '=', 't_cp_loader.cp_id')
            ->whereBetween(DB::raw('DATE(t_cp.cp_created_date)'), [$dateRange['from'], $dateRange['to']])
            ->where('t_cp.cp_is_deleted', 0);
        
        if ($divisionCode !== 'ALL') $query->where('t_cp.cp_division_code', $divisionCode);
        
        $query = $this->applyRoleFilters($query, 't_cp');
        
        $data = $query->select(
            't_cp_loader.cp_loader_employee_code',
            't_cp_loader.cp_loader_employee_name',
            't_cp_loader.cp_loader_type',
            DB::raw('COUNT(t_cp.cp_id) as total_trips'),
            DB::raw('SUM(t_cp.cp_netto) as total_netto'),
            DB::raw('AVG(t_cp_loader.cp_loader_percentage) as avg_percentage')
        )
        ->groupBy(
            't_cp_loader.cp_loader_employee_code',
            't_cp_loader.cp_loader_employee_name',
            't_cp_loader.cp_loader_type'
        )
        ->orderByDesc('total_trips')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                $row->cp_loader_employee_code,
                $row->cp_loader_employee_name,
                $row->cp_loader_type == 1 ? 'Driver' : 'Loader',
                $row->total_trips ?? 0,
                round($row->total_netto ?? 0, 2),
                round($row->avg_percentage ?? 0, 2),
            ];
        }
        
        $headers = ['Employee Code', 'Employee Name', 'Loader Type', 'Total Trips', 'Total Netto', 'Avg Percentage'];
        return $this->exportToCsv($rows, $headers, 'loader_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
