<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class GeneralAllocationReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.general_allocation.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        // General Allocation Report - General activity allocation
        $query = DB::table('t_general_allocation')
            ->leftJoin('m_activity', 't_general_allocation.activity_code', '=', 'm_activity.activity_code')
            ->leftJoin('m_block', 't_general_allocation.block_code', '=', 'm_block.block_code')
            ->leftJoin('m_employee', 't_general_allocation.employee_code', '=', 'm_employee.employee_code')
            ->whereBetween('t_general_allocation.allocation_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_general_allocation.division_code', $divisionCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_general_allocation');
        
        $query->select(
            't_general_allocation.allocation_date',
            't_general_allocation.division_code',
            't_general_allocation.block_code',
            'm_block.block_name',
            't_general_allocation.activity_code',
            'm_activity.activity_name',
            't_general_allocation.employee_code',
            'm_employee.employee_name',
            't_general_allocation.allocated_quantity',
            't_general_allocation.uom',
            't_general_allocation.notes'
        )
        ->orderBy('t_general_allocation.allocation_date', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('allocation_date', fn($row) => date('d-m-Y', strtotime($row->allocation_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_general_allocation')
            ->leftJoin('m_activity', 't_general_allocation.activity_code', '=', 'm_activity.activity_code')
            ->leftJoin('m_block', 't_general_allocation.block_code', '=', 'm_block.block_code')
            ->leftJoin('m_employee', 't_general_allocation.employee_code', '=', 'm_employee.employee_code')
            ->whereBetween('t_general_allocation.allocation_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_general_allocation.division_code', $divisionCode);
        $query = $this->applyRoleFilters($query, 't_general_allocation');
        
        $data = $query->select(
            't_general_allocation.allocation_date',
            't_general_allocation.division_code',
            't_general_allocation.block_code',
            'm_block.block_name',
            't_general_allocation.activity_code',
            'm_activity.activity_name',
            't_general_allocation.employee_code',
            'm_employee.employee_name',
            't_general_allocation.allocated_quantity',
            't_general_allocation.uom',
            't_general_allocation.notes'
        )
        ->orderBy('t_general_allocation.allocation_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->allocation_date)),
                $row->division_code ?? '',
                $row->block_code ?? '',
                $row->block_name ?? '',
                $row->activity_code ?? '',
                $row->activity_name ?? '',
                $row->employee_code ?? '',
                $row->employee_name ?? '',
                $row->allocated_quantity ?? 0,
                $row->uom ?? '',
                $row->notes ?? ''
            ];
        }
        
        $headers = ['Date', 'Division', 'Block Code', 'Block Name', 'Activity Code', 'Activity Name', 'Employee Code', 'Employee Name', 'Allocated Qty', 'UOM', 'Notes'];
        return $this->exportToCsv($rows, $headers, 'general_allocation_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
