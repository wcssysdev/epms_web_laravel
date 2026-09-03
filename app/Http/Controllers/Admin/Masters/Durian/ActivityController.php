<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Masters\BaseGlobalCrudController;
use Illuminate\Http\Request;

/** Durian Activity — global CRUD. */
class ActivityController extends BaseGlobalCrudController
{
    protected function tableName(): string    { return 'm_durian_activity'; }
    protected function resourceName(): string { return 'Durian Activity'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.activity'; }
    protected function routePrefix(): string  { return 'masters.durian.activity'; }

    protected function datatableColumns(): array
    {
        return [
            'activity_code'       => 'Code',
            'activity_group_code' => 'Group Code',
            'activity_name'       => 'Name',
            'updated_at'          => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'activity_code'       => 'required|string|max:100',
            'activity_group_code' => 'nullable|string|max:100',
            'activity_name'       => 'required|string|max:255',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'activity_code'       => strtoupper(trim($request->activity_code)),
            'activity_group_code' => trim((string) $request->activity_group_code),
            'activity_name'       => trim($request->activity_name),
        ];
    }
}
