<?php

namespace App\Http\Controllers\Transaction\Monitoring;

use App\Http\Controllers\BaseController;
use App\Traits\ScopesToAssistantDivisions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

/**
 * Read-only transaction monitoring base. Managers (Estate Manager, Asst Manager)
 * review submitted transactions over a date range. Asst Managers are division-scoped
 * where the underlying table has a division_code column.
 */
abstract class BaseMonitoringController extends BaseController
{
    use ScopesToAssistantDivisions;

    /** Eloquent query for the monitored table (already company-scoped via model). */
    abstract protected function baseQuery(): Builder;

    /** The date column used for range filtering. */
    abstract protected function dateColumn(): string;

    /** Column => label map shown in the DataTable. */
    abstract protected function datatableColumns(): array;

    /** Blade view prefix, e.g. 'transaction.monitoring.overtime'. */
    abstract protected function viewPrefix(): string;

    /** Route name prefix, e.g. 'transactions.monitoring.overtime'. */
    abstract protected function routePrefix(): string;

    /** Human title. */
    abstract protected function title(): string;

    /** Does the table support division scoping? Override to true where applicable. */
    protected function divisionScoped(): bool
    {
        return false;
    }

    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);

        return view($this->viewPrefix() . '.index', [
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'columns'     => $this->datatableColumns(),
            'from'        => $from,
            'to'          => $to,
        ]);
    }

    // ── DATATABLE ─────────────────────────────────────────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $query = $this->baseQuery()
            ->whereBetween($this->dateColumn(), [$from, $to]);

        // Division scope for Assistant Managers (where supported)
        if ($this->divisionScoped()) {
            $divisions = $this->approverDivisions();
            if ($this->hasNoApproverDivisions($divisions)) {
                $query->whereRaw('1 = 0'); // no divisions → no rows
            } elseif (is_array($divisions)) {
                $query->whereIn('division_code', $divisions);
            }
        }

        return DataTables::eloquent($query)->addIndexColumn()->make(true);
    }

    // ── HELPERS ─────────────────────────────────────────────────────────────
    /** @return array{0:string,1:string} [from, to] in Y-m-d */
    protected function resolveRange(Request $request): array
    {
        $sessionKey = $this->routePrefix() . '.range';
        $stored     = session($sessionKey, []);

        $from = $request->query('from', $stored['from'] ?? Carbon::today()->subDays(7)->toDateString());
        $to   = $request->query('to',   $stored['to']   ?? Carbon::today()->toDateString());

        // Normalise + guard: from <= to
        try {
            $from = Carbon::parse($from)->toDateString();
            $to   = Carbon::parse($to)->toDateString();
        } catch (\Throwable $e) {
            $from = Carbon::today()->subDays(7)->toDateString();
            $to   = Carbon::today()->toDateString();
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        session([$sessionKey => ['from' => $from, 'to' => $to]]);
        return [$from, $to];
    }
}
