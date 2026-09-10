<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class WorkdoneReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        
        if ($request->ajax()) {
            return $this->getDatatable($request);
        }
        
        $filters = $this->getStandardFilters($request);
        
        return view('reporting.transaction.workdone.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        $query = DB::table('t_workdone')
            ->leftJoin('m_employee', 't_workdone.employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_activity', 't_workdone.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween(DB::raw('DATE(t_workdone.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_workdone.division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_workdone.block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_workdone');
        
        $query->select(
            't_workdone.*',
            'm_employee.employee_name',
            'm_activity.activity_name'
        );
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('created_at', function ($row) {
                return date('d-m-Y', strtotime($row->created_at));
            })
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        $query = DB::table('t_workdone')
            ->leftJoin('m_employee', 't_workdone.employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_activity', 't_workdone.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween(DB::raw('DATE(t_workdone.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') {
            $query->where('t_workdone.division_code', $divisionCode);
        }
        
        if ($blockCode !== 'ALL') {
            $query->where('t_workdone.block_code', $blockCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_workdone');
        
        $data = $query->select(
            't_workdone.*',
            'm_employee.employee_name',
            'm_activity.activity_name'
        )->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->created_at)),
                $row->division_code,
                $row->block_code,
                $row->employee_code,
                $row->employee_name,
                $row->activity_code,
                $row->activity_name,
                $row->quantity ?? 0,
                $row->notes ?? '',
            ];
        }
        
        $headers = ['Date', 'Division', 'Block', 'Employee Code', 'Employee Name', 'Activity Code', 'Activity', 'Quantity', 'Notes'];
        $filename = 'workdone_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv';
        
        return $this->exportToCsv($rows, $headers, $filename);
    }
}
