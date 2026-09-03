<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Base for GLOBAL (non-tenant) CRUD masters — tables with no company_id column.
 * Reuses the generic CRUD flow but drops company scoping and does not inject
 * company_id on insert/update.
 */
abstract class BaseGlobalCrudController extends BaseGroupingController
{
    protected function baseQuery()
    {
        return DB::table($this->tableName());
    }

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
