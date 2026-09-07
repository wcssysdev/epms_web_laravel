<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Shared CRUD skeleton for simple operational transaction screens
 * (Attendance, Workdone, Harvester Assignment). Date-filtered listing +
 * server-side DataTable + create/store/edit/update/destroy, all company-
 * scoped (and estate-scoped for multi-estate roles via HasCompanyScope).
 *
 * Child controllers implement the model, columns, validation, mapping and
 * the form option data.
 */
abstract class BaseTransactionController extends BaseController
{
    use \App\Http\Controllers\Transaction\Concerns\GuardsSapIntegration;

    /** Fully-qualified Eloquent model class (uses HasCompanyScope). */
    abstract protected function modelClass(): string;

    /** Date column used for the range/day filter. */
    abstract protected function dateColumn(): string;

    /** DataTable columns [db_or_computed_col => label]. */
    abstract protected function datatableColumns(): array;

    /** Blade view prefix, e.g. 'transaction.attendance'. */
    abstract protected function viewPrefix(): string;

    /** Route name prefix, e.g. 'transactions.attendance'. */
    abstract protected function routePrefix(): string;

    /** Human title. */
    abstract protected function title(): string;

    /** Validation rules for store/update. */
    abstract protected function rules(Request $request): array;

    /** Map a validated request to a DB row (without company/audit fields). */
    abstract protected function mapRow(Request $request): array;

    /** Extra data for the form view (dropdown options). */
    abstract protected function formData(): array;

    /**
     * Optional string primary key generator for tables whose id is a
     * non-incrementing varchar (e.g. t_workdone). Return null to let the
     * database assign the id (auto-increment).
     */
    protected function generateId(): ?string
    {
        return null;
    }

    /** Optional per-row computed columns for the datatable. */
    protected function decorateDatatable($dt)
    {
        return $dt;
    }

    /** Whether this screen exposes CSV import/template buttons. */
    protected function hasCsv(): bool
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
            'hasCsv'      => $this->hasCsv(),
        ]);
    }

    // ── DATATABLE ─────────────────────────────────────────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        /** @var Builder $query */
        $query = $this->modelClass()::query()
            ->whereBetween($this->dateColumn(), [$from, $to])
            ->orderByDesc($this->dateColumn())
            ->orderByDesc('id');

        $dt = DataTables::eloquent($query)->addIndexColumn();
        $dt = $this->decorateDatatable($dt);

        return $dt->make(true);
    }

    // ── CREATE ──────────────────────────────────────────────────────────────
    public function create(): View
    {
        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => null,
        ], $this->formData()));
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->rules($request));

        $data = array_merge($this->mapRow($request), [
            'company_id' => $this->companyId(),
            'created_by' => $this->userName(),
            'updated_by' => $this->userName(),
        ]);

        if (($id = $this->generateId()) !== null) {
            $data['id'] = $id;
        }

        $model = $this->modelClass();
        $model::create($data);

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Created {$this->title()}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' saved successfully.');
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    public function edit($id): View|RedirectResponse
    {
        $item = $this->modelClass()::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;

        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => $item,
        ], $this->formData()));
    }

    // ── UPDATE ──────────────────────────────────────────────────────────────
    public function update(Request $request, $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = $this->modelClass()::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;

        $request->validate($this->rules($request));

        $item->update(array_merge($this->mapRow($request), [
            'updated_by' => $this->userName(),
        ]));

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
            "Updated {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' updated successfully.');
    }

    // ── DESTROY ─────────────────────────────────────────────────────────────
    public function destroy($id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = $this->modelClass()::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapDelete($item)) return $sap;
        $item->delete();

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
            "Deleted {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' deleted.');
    }

    // ── HELPERS ─────────────────────────────────────────────────────────────
    /** @return array{0:string,1:string} [from, to] in Y-m-d */
    protected function resolveRange(Request $request): array
    {
        $sessionKey = $this->routePrefix() . '.range';
        $stored     = session($sessionKey, []);

        $from = $request->query('from', $stored['from'] ?? Carbon::today()->subDays(7)->toDateString());
        $to   = $request->query('to',   $stored['to']   ?? Carbon::today()->toDateString());

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

    /** Divisions for the current estate (form dropdowns). */
    protected function divisions()
    {
        return \App\Models\Master\Division::byEstate($this->estateCode())
            ->orderBy('division_code')->get(['division_code', 'division_name']);
    }

    /** Blocks for the current estate (form dropdowns, filtered client-side). */
    protected function blocks()
    {
        return \App\Models\Master\Block::where('estate_code', $this->estateCode())
            ->orderBy('block_code')->get(['division_code', 'block_code', 'block_name']);
    }

    /** Active employees for the current estate. */
    protected function employees()
    {
        return \App\Models\Master\Employee::byEstate($this->estateCode())
            ->orderBy('employee_code')->get(['employee_code', 'employee_name', 'employee_division_code']);
    }
}
