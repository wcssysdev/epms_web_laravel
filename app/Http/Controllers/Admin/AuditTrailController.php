<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AuditTrailController extends BaseController
{
    // Transaction type labels
    const TYPE_LABELS = [
        1 => 'Auth',
        2 => 'Master Data',
        3 => 'Transaction',
        4 => 'Approval',
        5 => 'System',
    ];

    // Action type labels
    const ACTION_LABELS = [
        1 => 'Create',
        2 => 'Update',
        3 => 'Delete',
        4 => 'Approve',
        5 => 'Reject',
        6 => 'Login',
        7 => 'Logout',
        8 => 'Lock',
        9 => 'Unlock',
    ];

    public function index(): View
    {
        return view('admin.audit.index', [
            'typeLabels'   => self::TYPE_LABELS,
            'actionLabels' => self::ACTION_LABELS,
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = DB::table('audit_trail')
            ->where('company_id', $this->companyId())
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }
        if ($request->filled('user_code')) {
            $query->where('user_code', 'ilike', "%{$request->user_code}%");
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return DataTables::queryBuilder($query)
            ->addIndexColumn()
            ->addColumn('transaction_type_label', function ($row) {
                return self::TYPE_LABELS[$row->transaction_type] ?? $row->transaction_type;
            })
            ->addColumn('action_type_label', function ($row) {
                $label  = self::ACTION_LABELS[$row->action_type] ?? $row->action_type;
                $colors = [
                    'Create'  => 'bg-green-100 text-green-700',
                    'Update'  => 'bg-blue-100 text-blue-700',
                    'Delete'  => 'bg-red-100 text-red-700',
                    'Approve' => 'bg-purple-100 text-purple-700',
                    'Login'   => 'bg-teal-100 text-teal-700',
                    'Lock'    => 'bg-orange-100 text-orange-700',
                ];
                $cls = $colors[$label] ?? 'bg-gray-100 text-gray-700';
                return "<span class=\"text-xs font-medium px-2 py-0.5 rounded-full {$cls}\">{$label}</span>";
            })
            ->addColumn('created_at_fmt', fn($r) => \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i:s'))
            ->rawColumns(['action_type_label'])
            ->make(true);
    }
}
