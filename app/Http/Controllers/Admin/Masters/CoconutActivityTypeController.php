<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\Request;

/**
 * Coconut Activity Type — GLOBAL master (no company_id).
 * CRUD, gated to coconut-enabled companies at the route level.
 */
class CoconutActivityTypeController extends BaseGlobalCrudController
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
}
