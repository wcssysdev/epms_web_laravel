<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CoconutChitReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.coconut_chit.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        $query = DB::table('t_coconut_oph')
            ->leftJoin('m_employee as harvester', 't_coconut_oph.harvester_employee_code', '=', 'harvester.employee_code')
            ->leftJoin('m_employee as checker', 't_coconut_oph.checker_employee_code', '=', 'checker.employee_code')
            ->whereBetween(DB::raw('DATE(t_coconut_oph.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_coconut_oph.division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_coconut_oph.block_code', $blockCode);
        
        $query = $this->applyRoleFilters($query, 't_coconut_oph');
        $query->select(
            't_coconut_oph.*',
            'harvester.employee_name as harvester_name',
            'checker.employee_name as checker_name'
        );
        
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
        $blockCode = $this->getRequestedBlock($request);
        
        $query = DB::table('t_coconut_oph')
            ->leftJoin('m_employee as harvester', 't_coconut_oph.harvester_employee_code', '=', 'harvester.employee_code')
            ->leftJoin('m_employee as checker', 't_coconut_oph.checker_employee_code', '=', 'checker.employee_code')
            ->whereBetween(DB::raw('DATE(t_coconut_oph.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_coconut_oph.division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_coconut_oph.block_code', $blockCode);
        
        $query = $this->applyRoleFilters($query, 't_coconut_oph');
        $data = $query->select(
            't_coconut_oph.*',
            'harvester.employee_name as harvester_name',
            'checker.employee_name as checker_name'
        )->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->created_at)),
                $row->division_code,
                $row->block_code,
                $row->harvester_employee_code,
                $row->harvester_name,
                $row->checker_employee_code,
                $row->checker_name,
                $row->nuts_total ?? 0,
            ];
        }
        
        $headers = ['Date', 'Division', 'Block', 'Harvester Code', 'Harvester Name', 'Checker Code', 'Checker Name', 'Total Nuts'];
        return $this->exportToCsv($rows, $headers, 'coconut_chit_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
