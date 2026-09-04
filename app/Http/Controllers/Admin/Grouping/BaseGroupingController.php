<?php

namespace App\Http\Controllers\Admin\Grouping;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

abstract class BaseGroupingController extends BaseController
{
    abstract protected function tableName(): string;
    abstract protected function resourceName(): string;
    abstract protected function datatableColumns(): array;
    abstract protected function viewPrefix(): string;
    abstract protected function routePrefix(): string;
    abstract protected function storeData(Request $request): array;
    abstract protected function storeValidation(): array;

    protected function baseQuery()
    {
        return DB::table($this->tableName())->where('company_id', $this->companyId());
    }

    /** Whether this master exposes CSV upload/template/export buttons. */
    protected function hasCsv(): bool
    {
        return false;
    }

    /** Whether this master exposes a per-row "Print QR" action. */
    protected function hasQr(): bool
    {
        return false;
    }

    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index()
    {
        return view($this->viewPrefix() . '.index', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'columns'      => $this->datatableColumns(),
            'totalRows'    => $this->baseQuery()->count(),
            'hasCsv'       => $this->hasCsv(),
            'hasQr'        => $this->hasQr(),
        ]);
    }

    // ── DATATABLE ─────────────────────────────────────────────────────────────
    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery();
        return DataTables::query($query)->addIndexColumn()->make(true);
    }

    // ── CREATE form ───────────────────────────────────────────────────────────
    public function create()
    {
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => null,
        ]);
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->storeValidation());

        $data = array_merge($this->storeData($request), [
            'company_id' => $this->companyId(),
            'created_by' => $this->userName(),
            'updated_by' => $this->userName(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->tableName())->insert($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE,
            "Created {$this->resourceName()}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' added successfully.');
    }

    // ── EDIT form ─────────────────────────────────────────────────────────────
    public function edit(int $id)
    {
        $item = $this->baseQuery()->where('id', $id)->first();
        abort_unless($item, 404);
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => $item,
        ]);
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->storeValidation());

        $data = array_merge($this->storeData($request), [
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ]);

        $this->baseQuery()->where('id', $id)->update($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE,
            "Updated {$this->resourceName()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' updated successfully.');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────
    public function destroy(int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->baseQuery()->where('id', $id)->delete();
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_DELETE,
            "Deleted {$this->resourceName()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' deleted.');
    }
}
