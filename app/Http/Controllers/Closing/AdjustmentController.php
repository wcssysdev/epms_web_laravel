<?php

namespace App\Http\Controllers\Closing;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Adjustment log viewer — read-only screen for role 4 (Estate Staff) and
 * IT Staff. Shows the t_adjustment audit log filtered by date range and type.
 * Replicates CI3 adjustment/Adjustment.php (index / filter / detail).
 */
class AdjustmentController extends BaseController
{
    private array $types = [
        'All','Attendance','OPH','Work Completion','Overtime',
        'VRA','Checkpoint','FDN','Coconut HC','Coconut FDN',
    ];

    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        return view('closing.adjustment.index', [
            'title'   => 'Adjustment Log',
            'from'    => $from,
            'to'      => $to,
            'types'   => $this->types,
            'selType' => $request->query('type', 'All'),
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $type = $request->query('type', 'All');

        $q = DB::table('t_adjustment')
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->whereBetween('date', [$from, $to])
            ->when($type !== 'All', fn($q) => $q->where('adjustment_type', $type))
            ->orderByDesc('date')->orderByDesc('time');

        return DataTables::query($q)->addIndexColumn()->make(true);
    }

    private function resolveRange(Request $request): array
    {
        $stored = session('closing.adjustment.range', []);
        $from   = $request->query('from', $stored['from'] ?? Carbon::today()->subDays(7)->toDateString());
        $to     = $request->query('to',   $stored['to']   ?? Carbon::today()->toDateString());
        try {
            $from = Carbon::parse($from)->toDateString();
            $to   = Carbon::parse($to)->toDateString();
        } catch (\Throwable $e) {
            $from = Carbon::today()->subDays(7)->toDateString();
            $to   = Carbon::today()->toDateString();
        }
        if ($from > $to) [$from, $to] = [$to, $from];
        session(['closing.adjustment.range' => ['from' => $from, 'to' => $to]]);
        return [$from, $to];
    }
}
