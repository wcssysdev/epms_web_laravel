<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PanenAllocationReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.panen_allocation.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        // Panen (Harvesting) Allocation Report
        $query = DB::table('t_harvesting_plan')
            ->leftJoin('m_block', 't_harvesting_plan.harvesting_block_code', '=', 'm_block.block_code')
            ->leftJoin('m_employee as harvester', 't_harvesting_plan.harvester_employee_code', '=', 'harvester.employee_code')
            ->leftJoin('m_employee as mandor', 't_harvesting_plan.mandor_employee_code', '=', 'mandor.employee_code')
            ->whereBetween('t_harvesting_plan.harvesting_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_harvesting_plan.harvesting_division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_harvesting_plan.harvesting_block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_harvesting_plan');
        
        $query->select(
            't_harvesting_plan.harvesting_date',
            't_harvesting_plan.harvesting_division_code',
            't_harvesting_plan.harvesting_block_code',
            'm_block.block_name',
            't_harvesting_plan.harvester_employee_code',
            'harvester.employee_name as harvester_name',
            't_harvesting_plan.mandor_employee_code',
            'mandor.employee_name as mandor_name',
            't_harvesting_plan.target_bunches',
            't_harvesting_plan.target_loose_fruit',
            't_harvesting_plan.allocated_hectare'
        )
        ->orderBy('t_harvesting_plan.harvesting_date', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('harvesting_date', fn($row) => date('d-m-Y', strtotime($row->harvesting_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $request->get('block', 'ALL');
        
        $query = DB::table('t_harvesting_plan')
            ->leftJoin('m_block', 't_harvesting_plan.harvesting_block_code', '=', 'm_block.block_code')
            ->leftJoin('m_employee as harvester', 't_harvesting_plan.harvester_employee_code', '=', 'harvester.employee_code')
            ->leftJoin('m_employee as mandor', 't_harvesting_plan.mandor_employee_code', '=', 'mandor.employee_code')
            ->whereBetween('t_harvesting_plan.harvesting_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_harvesting_plan.harvesting_division_code', $divisionCode);
        if ($blockCode !== 'ALL') $query->where('t_harvesting_plan.harvesting_block_code', $blockCode);
        $query = $this->applyRoleFilters($query, 't_harvesting_plan');
        
        $data = $query->select(
            't_harvesting_plan.harvesting_date',
            't_harvesting_plan.harvesting_division_code',
            't_harvesting_plan.harvesting_block_code',
            'm_block.block_name',
            't_harvesting_plan.harvester_employee_code',
            'harvester.employee_name as harvester_name',
            't_harvesting_plan.mandor_employee_code',
            'mandor.employee_name as mandor_name',
            't_harvesting_plan.target_bunches',
            't_harvesting_plan.target_loose_fruit',
            't_harvesting_plan.allocated_hectare'
        )
        ->orderBy('t_harvesting_plan.harvesting_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->harvesting_date)),
                $row->harvesting_division_code ?? '',
                $row->harvesting_block_code ?? '',
                $row->block_name ?? '',
                $row->harvester_employee_code ?? '',
                $row->harvester_name ?? '',
                $row->mandor_employee_code ?? '',
                $row->mandor_name ?? '',
                $row->target_bunches ?? 0,
                $row->target_loose_fruit ?? 0,
                $row->allocated_hectare ?? 0
            ];
        }
        
        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Harvester Code', 'Harvester Name', 'Mandor Code', 'Mandor Name', 'Target Bunches', 'Target Loose Fruit', 'Allocated Ha'];
        return $this->exportToCsv($rows, $headers, 'panen_allocation_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
