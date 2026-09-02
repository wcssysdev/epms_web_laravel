<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Base controller for GLOBAL lookup tables (no company_id).
 * Managed by Super Admin / Country Admin only.
 */
abstract class BaseGlobalLookupController extends BaseController
{
    abstract protected function tableName(): string;
    abstract protected function resourceName(): string;
    abstract protected function primaryKey(): string;
    abstract protected function datatableColumns(): array;
    abstract protected function csvHeaders(): array;
    abstract protected function formFields(): array;  // [field => validation_rule]
    abstract protected function mapRow(array $data): array;

    protected function viewPrefix(): string
    {
        return 'admin.masters.global.' . strtolower(str_replace(' ', '_', $this->resourceName()));
    }

    protected function routePrefix(): string
    {
        return 'masters.global.' . strtolower(str_replace(' ', '_', $this->resourceName()));
    }

    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $total = DB::table($this->tableName())->count();
        return view($this->viewPrefix() . '.index', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'columns'      => $this->datatableColumns(),
            'totalRows'    => $total,
        ]);
    }

    // ── DATATABLE ─────────────────────────────────────────────────────────────
    public function getDatatable(): JsonResponse
    {
        $query = DB::table($this->tableName())->orderBy($this->primaryKey());
        return DataTables::queryBuilder($query)->addIndexColumn()->make(true);
    }

    // ── ADD form ──────────────────────────────────────────────────────────────
    public function add()
    {
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => null,
        ]);
    }

    // ── SAVE ──────────────────────────────────────────────────────────────────
    public function save(Request $request): RedirectResponse
    {
        $request->validate($this->formFields());
        $data = $this->mapRow($request->all());
        $data['created_by'] = $this->userName();
        $data['updated_by'] = $this->userName();
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::table($this->tableName())->insert($data);
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE, "Created {$this->resourceName()}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' added successfully.');
    }

    // ── EDIT form ─────────────────────────────────────────────────────────────
    public function edit(int $id)
    {
        $item = DB::table($this->tableName())->where($this->primaryKey(), $id)->first();
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
        $request->validate($this->formFields());
        $data = $this->mapRow($request->all());
        $data['updated_by'] = $this->userName();
        $data['updated_at'] = now();
        DB::table($this->tableName())->where($this->primaryKey(), $id)->update($data);
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE, "Updated {$this->resourceName()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' updated successfully.');
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    public function destroy(int $id): RedirectResponse
    {
        DB::table($this->tableName())->where($this->primaryKey(), $id)->delete();
        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_DELETE, "Deleted {$this->resourceName()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' deleted.');
    }

    // ── GENERATE CSV template ─────────────────────────────────────────────────
    public function generateCsv(): Response
    {
        $headers  = $this->csvHeaders();
        $filename = strtolower(str_replace(' ', '_', $this->resourceName())) . '_template.csv';
        return response()->stream(function () use ($headers) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $headers);
            fputcsv($h, array_fill(0, count($headers), ''));
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── LOOKUP (for dropdowns) ────────────────────────────────────────────────
    public function lookup(): JsonResponse
    {
        $data = DB::table($this->tableName())
            ->orderBy($this->primaryKey())
            ->get();
        return $this->jsonSuccess('OK', $data);
    }
}
