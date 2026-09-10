<?php

namespace App\Http\Controllers\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AuditTrailController extends BaseReportingController
{
    public function index(Request $request)
    {
        $this->authorize();
        
        if ($request->ajax()) {
            return $this->getDatatable($request);
        }
        
        $filters = $this->getStandardFilters($request);
        $transactionCode = $request->input('transaction_code', 'ALL');
        
        // Get transaction types for filter dropdown
        $transactionTypes = DB::table('audit_trail')
            ->select('audit_trail_transaction')
            ->distinct()
            ->whereNotNull('audit_trail_transaction')
            ->orderBy('audit_trail_transaction')
            ->pluck('audit_trail_transaction')
            ->toArray();
        
        return view('reporting.audit_trail.index', array_merge($filters, [
            'transaction_code' => $transactionCode,
            'transaction_types' => $transactionTypes,
        ]));
    }

    protected function getDatatable(Request $request)
    {
        $dateRange = $this->getDateRange($request);
        $transactionCode = $request->input('transaction_code', 'ALL');
        
        $query = DB::table('audit_trail')
            ->whereBetween('audit_trail_created_date', [$dateRange['from'], $dateRange['to']])
            ->whereNotNull('audit_trail_transaction');
        
        if ($transactionCode !== 'ALL') {
            $query->where('audit_trail_transaction', $transactionCode);
        }
        
        $query->orderBy('audit_trail_id', 'desc');
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('date_time', function ($row) {
                return date('d-m-Y', strtotime($row->audit_trail_created_date)) . ' ' . $row->audit_trail_created_time;
            })
            ->addColumn('action', function ($row) {
                return '<a href="' . route('reporting.audit-trail.detail', ['id' => $row->audit_trail_id, 'type' => $row->audit_trail_transaction]) . '" class="btn btn-sm btn-info" target="_blank">
                    <i class="fa fa-eye"></i> View Details
                </a>';
            })
            ->editColumn('audit_trail_type', function ($row) {
                $badges = [
                    'CREATE' => '<span class="badge badge-success">CREATE</span>',
                    'UPDATE' => '<span class="badge badge-warning">UPDATE</span>',
                    'DELETE' => '<span class="badge badge-danger">DELETE</span>',
                ];
                return $badges[$row->audit_trail_type] ?? $row->audit_trail_type;
            })
            ->editColumn('audit_trail_description', function ($row) {
                $desc = $row->audit_trail_description;
                if (strlen($desc) > 100) {
                    return substr($desc, 0, 100) . '...';
                }
                return $desc;
            })
            ->rawColumns(['action', 'audit_trail_type'])
            ->make(true);
    }

    public function detail($id, $type)
    {
        $this->authorize();
        
        $audit = DB::table('audit_trail')
            ->where('audit_trail_id', $id)
            ->first();
        
        if (!$audit) {
            abort(404, 'Audit trail record not found');
        }
        
        // Parse JSON description
        $description = str_replace(["\t", "\n"], "", $audit->audit_trail_description);
        $data = json_decode($description, true);
        
        $before = $data[0]['before'] ?? [];
        $after = $data[0]['after'] ?? [];
        
        return view('reporting.audit_trail.detail', [
            'audit' => $audit,
            'before' => $before,
            'after' => $after,
            'transaction_type' => $type,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize();
        
        $dateRange = $this->getDateRange($request);
        $transactionCode = $request->input('transaction_code', 'ALL');
        
        $query = DB::table('audit_trail')
            ->whereBetween('audit_trail_created_date', [$dateRange['from'], $dateRange['to']])
            ->whereNotNull('audit_trail_transaction');
        
        if ($transactionCode !== 'ALL') {
            $query->where('audit_trail_transaction', $transactionCode);
        }
        
        $data = $query->orderBy('audit_trail_id', 'desc')->get();
        
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                $row->audit_trail_id,
                date('d-m-Y', strtotime($row->audit_trail_created_date)),
                $row->audit_trail_created_time,
                $row->audit_trail_transaction,
                $row->audit_trail_type,
                $row->audit_trail_user_name,
                $row->audit_trail_description,
            ];
        }
        
        $headers = [
            'ID',
            'Date',
            'Time',
            'Transaction',
            'Type',
            'User',
            'Description',
        ];
        
        $filename = 'audit_trail_' . $dateRange['from'] . '_to_' . $dateRange['to'] . '.csv';
        
        return $this->exportToCsv($rows, $headers, $filename);
    }
}
