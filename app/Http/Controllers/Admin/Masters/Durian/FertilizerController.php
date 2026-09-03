<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Masters\BaseGlobalCrudController;
use Illuminate\Http\Request;

/** Durian Fertilizer — global CRUD. */
class FertilizerController extends BaseGlobalCrudController
{
    protected function tableName(): string    { return 'm_durian_fertilizer'; }
    protected function resourceName(): string { return 'Durian Fertilizer'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.fertilizer'; }
    protected function routePrefix(): string  { return 'masters.durian.fertilizer'; }

    protected function datatableColumns(): array
    {
        return [
            'fertilizer_code' => 'Code',
            'fertilizer_desc' => 'Description',
            'updated_at'      => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'fertilizer_code' => 'required|string|max:100',
            'fertilizer_desc' => 'required|string|max:255',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'fertilizer_code' => strtoupper(trim($request->fertilizer_code)),
            'fertilizer_desc' => trim($request->fertilizer_desc),
        ];
    }
}
