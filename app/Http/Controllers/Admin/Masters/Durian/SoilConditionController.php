<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Masters\BaseGlobalCrudController;
use Illuminate\Http\Request;

/** Durian Soil Condition — global CRUD. */
class SoilConditionController extends BaseGlobalCrudController
{
    protected function tableName(): string    { return 'm_durian_soil_condition'; }
    protected function resourceName(): string { return 'Durian Soil Condition'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.soil_condition'; }
    protected function routePrefix(): string  { return 'masters.durian.soil_condition'; }

    protected function datatableColumns(): array
    {
        return [
            'soil_code'    => 'Code',
            'soil_texture' => 'Texture',
            'updated_at'   => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'soil_code'    => 'required|string|max:100',
            'soil_texture' => 'required|string|max:255',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'soil_code'    => strtoupper(trim($request->soil_code)),
            'soil_texture' => trim($request->soil_texture),
        ];
    }
}
