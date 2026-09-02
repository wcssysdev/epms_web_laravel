<?php

namespace App\Http\Controllers\Admin\Masters;

class UomController extends BaseGlobalLookupController
{
    protected function tableName(): string    { return 'm_uom'; }
    protected function resourceName(): string { return 'UOM'; }
    protected function primaryKey(): string   { return 'id'; }
    protected function viewPrefix(): string   { return 'admin.masters.global.uom'; }
    protected function routePrefix(): string  { return 'masters.global.uom'; }

    protected function datatableColumns(): array
    {
        return ['uom_code' => 'Code', 'uom_desc' => 'Description', 'updated_at' => 'Last Updated'];
    }

    protected function csvHeaders(): array
    {
        return ['uom_code', 'uom_desc'];
    }

    protected function formFields(): array
    {
        return ['uom_code' => 'required|string|max:50', 'uom_desc' => 'required|string|max:255'];
    }

    protected function mapRow(array $data): array
    {
        return ['uom_code' => strtoupper(trim($data['uom_code'])), 'uom_desc' => trim($data['uom_desc'])];
    }
}
