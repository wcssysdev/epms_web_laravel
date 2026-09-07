<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\MillGraderOph;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * OPH (Mill Grader) — read-only monitoring (t_mill_grader_oph). Data comes
 * from the mill grader mobile flow; no web create/edit/delete.
 */
class OphMillGraderController extends BaseController
{
    protected function columns(): array
    {
        return [
            'division_code'        => 'Division',
            'block_code'           => 'Block',
            'tph_code'             => 'TPH',
            'grader_employee_name' => 'Grader',
            'integration_status'   => 'SAP Status',
        ];
    }

    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);

        return view('transaction.oph_mill_grader.index', [
            'title'       => 'OPH (Mill Grader)',
            'routePrefix' => 'transactions.oph_mill_grader',
            'columns'     => $this->columns(),
            'from'        => $from,
            'to'          => $to,
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $query = MillGraderOph::query()
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at');

        return DataTables::eloquent($query)->addIndexColumn()->make(true);
    }

    public function detail(string $id): View
    {
        $item = MillGraderOph::query()->whereKey($id)->firstOrFail();

        return view('transaction.oph_mill_grader.detail', [
            'title' => 'OPH (Mill Grader) Detail',
            'item'  => $item,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function resolveRange(Request $request): array
    {
        $from = $request->query('from', Carbon::today()->subDays(7)->toDateString());
        $to   = $request->query('to',   Carbon::today()->toDateString());
        try {
            $from = Carbon::parse($from)->toDateString();
            $to   = Carbon::parse($to)->toDateString();
        } catch (\Throwable $e) {
            $from = Carbon::today()->subDays(7)->toDateString();
            $to   = Carbon::today()->toDateString();
        }
        if ($from > $to) [$from, $to] = [$to, $from];
        return [$from, $to];
    }
}
