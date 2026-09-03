<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Coconut Activity Type — GLOBAL master (no company_id column).
 * CRUD, gated to coconut-enabled companies at the route level.
 */
class CoconutActivityTypeController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_coconut_activity_type'; }
    protected function resourceName(): string { return 'Coconut Activity Type'; }
    protected function viewPrefix(): string   { return 'admin.masters.coconut_activity_type'; }
    protected function routePrefix(): string  { return 'masters.coconut_activity_type'; }

    protected function datatableColumns(): array
    {
        return [
            'coconut_activity_type_code' => 'Code',
            'coconut_activity_type_desc' => 'Description',
            'updated_at'                 => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'coconut_activity_type_code' => 'required|string|max:100',
            'coconut_activity_type_desc' => 'required|string|max:255',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'coconut_activity_type_code' => strtoupper(trim($request->coconut_activity_type_code)),
            'coconut_activity_type_desc' => trim($request->coconut_activity_type_desc),
        ];
    }

    // ── Global table: no company_id scoping ───────────────────────────────────
    protected function baseQuery()
    {
        return DB::table($this->tableName());
    }

    // Override store/update to avoid injecting company_id (column doesn't exist)
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->storeValidation());

        DB::table($this->tableName())->insert(array_merge($this->storeData($request), [
            'created_by' => $this->userName(),
            'updated_by' => $this->userName(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE,
            "Created {$this->resourceName()}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' added successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->storeValidation());

        DB::table($this->tableName())->where('id', $id)->update(array_merge($this->storeData($request), [
            'updated_by' => $this->userName(),
            'updated_at' => now(),
        ]));

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE,
            "Updated {$this->resourceName()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->resourceName() . ' updated successfully.');
    }
}
