<?php

namespace App\Http\Controllers\Reporting\Transaction;

use App\Http\Controllers\Reporting\BaseReportingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class VraReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        
        $filters = $this->getStandardFilters($request);
        
        // Add work center and worktype options
        $filters['work_centers'] = DB::table('m_work_center')
            ->select('work_center_code', 'work_center_name')
            ->orderBy('work_center_code')
            ->get();
            
        $filters['worktypes'] = DB::table('m_worktype')
            ->select('worktype_code', 'worktype_name')
            ->orderBy('worktype_code')
            ->get();
        
        return view('reporting.transaction.vra.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $workCenterCode = $request->get('work_center', 'ALL');
        $worktypeCode = $request->get('worktype', 'ALL');
        
        // VRA Report - Vehicle Request Authorization
        $query = DB::table('t_vra')
            ->leftJoin('m_vra', 't_vra.vra_code', '=', 'm_vra.vra_order_number')
            ->leftJoin('m_work_center', 't_vra.vra_work_center', '=', 'm_work_center.work_center_code')
            ->leftJoin('m_worktype', 't_vra.vra_reason', '=', 'm_worktype.worktype_code')
            ->whereBetween(DB::raw('DATE(t_vra.vra_posting_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($workCenterCode !== 'ALL') {
            $query->where('t_vra.vra_work_center', $workCenterCode);
        }
        
        if ($worktypeCode !== 'ALL') {
            $query->where('t_vra.vra_reason', $worktypeCode);
        }
        
        $query = $this->applyRoleFilters($query, 't_vra');
        
        $query->select(
            't_vra.vra_id',
            't_vra.vra_code',
            DB::raw('DATE(t_vra.vra_posting_date) as posting_date'),
            't_vra.vra_work_center',
            'm_work_center.work_center_name',
            't_vra.vra_reason',
            'm_worktype.worktype_name',
            't_vra.vra_start_time',
            't_vra.vra_end_time',
            't_vra.vra_measurement',
            't_vra.vra_actual',
            'm_vra.vra_license_number'
        )
        ->orderBy('posting_date', 'desc')
        ->orderBy('t_vra.vra_code');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('posting_date', fn($row) => date('d-m-Y', strtotime($row->posting_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $workCenterCode = $request->get('work_center', 'ALL');
        $worktypeCode = $request->get('worktype', 'ALL');
        
        $query = DB::table('t_vra')
            ->leftJoin('m_vra', 't_vra.vra_code', '=', 'm_vra.vra_order_number')
            ->leftJoin('m_work_center', 't_vra.vra_work_center', '=', 'm_work_center.work_center_code')
            ->leftJoin('m_worktype', 't_vra.vra_reason', '=', 'm_worktype.worktype_code')
            ->whereBetween(DB::raw('DATE(t_vra.vra_posting_date)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($workCenterCode !== 'ALL') $query->where('t_vra.vra_work_center', $workCenterCode);
        if ($worktypeCode !== 'ALL') $query->where('t_vra.vra_reason', $worktypeCode);
        
        $query = $this->applyRoleFilters($query, 't_vra');
        
        $data = $query->select(
            't_vra.vra_code',
            DB::raw('DATE(t_vra.vra_posting_date) as posting_date'),
            'm_work_center.work_center_name',
            'm_worktype.worktype_name',
            't_vra.vra_start_time',
            't_vra.vra_end_time',
            't_vra.vra_measurement',
            't_vra.vra_actual',
            'm_vra.vra_license_number'
        )
        ->orderBy('posting_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                date('d-m-Y', strtotime($row->posting_date)),
                $row->vra_code,
                $row->work_center_name ?? '',
                $row->worktype_name ?? '',
                $row->vra_start_time ?? '',
                $row->vra_end_time ?? '',
                $row->vra_measurement ?? 0,
                $row->vra_actual ?? 0,
                $row->vra_license_number ?? '',
            ];
        }
        
        $headers = ['Date', 'VRA Code', 'Work Center', 'Work Type', 'Start Time', 'End Time', 'Measurement', 'Actual', 'License Number'];
        return $this->exportToCsv($rows, $headers, 'vra_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
