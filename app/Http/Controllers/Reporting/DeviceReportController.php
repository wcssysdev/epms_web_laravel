<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class DeviceReportController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        if ($request->ajax()) return $this->getDatatable($request);
        
        $filters = $this->getStandardFilters($request);
        $filters['devices'] = DB::table('m_device')->select('device_id', 'device_name')->orderBy('device_name')->get();
        
        return view('reporting.device.index', $filters);
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $deviceId = $request->get('device_id', 'ALL');
        
        // Device Report - Device usage and activity tracking
        $query = DB::table('t_device_log')
            ->leftJoin('m_device', 't_device_log.device_id', '=', 'm_device.device_id')
            ->leftJoin('tc_user', 't_device_log.user_id', '=', 'tc_user.user_id')
            ->whereBetween(DB::raw('DATE(t_device_log.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($deviceId !== 'ALL') {
            $query->where('t_device_log.device_id', $deviceId);
        }
        
        $query->select(
            't_device_log.device_id',
            'm_device.device_name',
            't_device_log.user_id',
            'tc_user.user_name',
            't_device_log.activity_type',
            DB::raw('DATE(t_device_log.created_at) as log_date'),
            DB::raw('COUNT(*) as activity_count')
        )
        ->groupBy(
            't_device_log.device_id',
            'm_device.device_name',
            't_device_log.user_id',
            'tc_user.user_name',
            't_device_log.activity_type',
            'log_date'
        )
        ->orderBy('log_date', 'desc')
        ->orderBy('activity_count', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('log_date', fn($row) => date('d-m-Y', strtotime($row->log_date)))
            ->make(true);
    }

    public function export(Request $request)
    {
        $this->authorize();
        $dateRange = $this->getDateRange($request);
        $deviceId = $request->get('device_id', 'ALL');
        
        $query = DB::table('t_device_log')
            ->leftJoin('m_device', 't_device_log.device_id', '=', 'm_device.device_id')
            ->leftJoin('tc_user', 't_device_log.user_id', '=', 'tc_user.user_id')
            ->whereBetween(DB::raw('DATE(t_device_log.created_at)'), [$dateRange['from'], $dateRange['to']]);
        
        if ($deviceId !== 'ALL') $query->where('t_device_log.device_id', $deviceId);
        
        $data = $query->select(
            't_device_log.device_id',
            'm_device.device_name',
            't_device_log.user_id',
            'tc_user.user_name',
            't_device_log.activity_type',
            DB::raw('DATE(t_device_log.created_at) as log_date'),
            DB::raw('COUNT(*) as activity_count')
        )
        ->groupBy('t_device_log.device_id', 'm_device.device_name', 't_device_log.user_id', 'tc_user.user_name', 't_device_log.activity_type', 'log_date')
        ->orderBy('log_date', 'desc')
        ->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [date('d-m-Y', strtotime($row->log_date)), $row->device_id ?? '', $row->device_name ?? '', $row->user_id ?? '', $row->user_name ?? '', $row->activity_type ?? '', $row->activity_count ?? 0];
        }
        
        $headers = ['Date', 'Device ID', 'Device Name', 'User ID', 'User Name', 'Activity Type', 'Activity Count'];
        return $this->exportToCsv($rows, $headers, 'device_report_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv');
    }
}
