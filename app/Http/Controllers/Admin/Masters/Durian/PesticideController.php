<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Masters\BaseGlobalCrudController;
use Illuminate\Http\Request;

/** Durian Pesticide — global CRUD. */
class PesticideController extends BaseGlobalCrudController
{
    protected function tableName(): string    { return 'm_durian_pesticide'; }
    protected function resourceName(): string { return 'Durian Pesticide'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.pesticide'; }
    protected function routePrefix(): string  { return 'masters.durian.pesticide'; }

    protected function datatableColumns(): array
    {
        return [
            'pesticide_code' => 'Code',
            'pesticide_desc' => 'Description',
            'updated_at'     => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'pesticide_code' => 'required|string|max:100',
            'pesticide_desc' => 'required|string|max:1000',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'pesticide_code' => strtoupper(trim($request->pesticide_code)),
            'pesticide_desc' => trim($request->pesticide_desc),
        ];
    }
}
