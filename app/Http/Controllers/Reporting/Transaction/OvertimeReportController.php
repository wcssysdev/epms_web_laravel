<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class OvertimeReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        return view('reporting.transaction.overtime.index', $this->getStandardFilters($request));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_overtime')
            ->leftJoin('m_employee', 't_overtime.employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_activity', 't_overtime.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween('t_overtime.overtime_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_overtime.division_code', $divisionCode);
        $query = $this->applyRoleFilters($query, 't_overtime');
        $query->select('t_overtime.*', 'm_employee.employee_name', 'm_activity.activity_name');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('overtime_date', fn($row) => date('d-m-Y', strtotime($row->overtime_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        
        $query = DB::table('t_overtime')
            ->leftJoin('m_employee', 't_overtime.employee_code', '=', 'm_employee.employee_code')
            ->leftJoin('m_activity', 't_overtime.activity_code', '=', 'm_activity.activity_code')
            ->whereBetween('t_overtime.overtime_date', [$dateRange['from'], $dateRange['to']]);
        
        if ($divisionCode !== 'ALL') $query->where('t_overtime.division_code', $divisionCode);
        $query = $this->applyRoleFilters($query, 't_overtime');
        $data = $query->select('t_overtime.*', 'm_employee.employee_name', 'm_activity.activity_name')->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->overtime_date)),
                $row->division_code,
                $row->employee_code,
                $row->employee_name,
                $row->activity_code,
                $row->activity_name,
                $row->duration_hours ?? 0,
            ];
        }
        
        $headers = ['Date', 'Division', 'Employee Code', 'Employee Name', 'Activity Code', 'Activity', 'Duration (Hours)'];
        return $this->exportToCsv($rows, $headers, 'overtime_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
