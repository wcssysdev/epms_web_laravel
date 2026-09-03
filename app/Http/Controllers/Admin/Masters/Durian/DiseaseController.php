<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Masters\BaseGlobalCrudController;
use Illuminate\Http\Request;

/** Durian Disease — global CRUD. */
class DiseaseController extends BaseGlobalCrudController
{
    protected function tableName(): string    { return 'm_durian_disease'; }
    protected function resourceName(): string { return 'Durian Disease'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.disease'; }
    protected function routePrefix(): string  { return 'masters.durian.disease'; }

    protected function datatableColumns(): array
    {
        return [
            'disease_code' => 'Code',
            'disease_desc' => 'Description',
            'updated_at'   => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'disease_code' => 'required|string|max:100',
            'disease_desc' => 'required|string|max:255',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'disease_code' => strtoupper(trim($request->disease_code)),
            'disease_desc' => trim($request->disease_desc),
        ];
    }
}
