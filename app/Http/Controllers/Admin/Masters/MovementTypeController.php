<?php

namespace App\Http\Controllers\Admin\Masters;

class MovementTypeController extends BaseGlobalLookupController
{
    protected function tableName(): string    { return 'm_movement_type'; }
    protected function resourceName(): string { return 'Movement Type'; }
    protected function primaryKey(): string   { return 'mvt_type_id'; }
    protected function viewPrefix(): string   { return 'admin.masters.global.movement_type'; }
    protected function routePrefix(): string  { return 'masters.global.movement_type'; }

    protected function datatableColumns(): array
    {
        return [
            'mvt_type_code'     => 'Code',
            'mvt_type_doc_type' => 'Doc Type',
            'mvt_type_desc'     => 'Description',
            'updated_at'        => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['mvt_type_code', 'mvt_type_doc_type', 'mvt_type_desc'];
    }

    protected function formFields(): array
    {
        return [
            'mvt_type_code'     => 'required|string|max:50',
            'mvt_type_doc_type' => 'required|string|max:50',
            'mvt_type_desc'     => 'required|string|max:255',
        ];
    }

    protected function mapRow(array $data): array
    {
        return [
            'mvt_type_code'     => strtoupper(trim($data['mvt_type_code'])),
            'mvt_type_doc_type' => trim($data['mvt_type_doc_type']),
            'mvt_type_desc'     => trim($data['mvt_type_desc']),
        ];
    }
}
