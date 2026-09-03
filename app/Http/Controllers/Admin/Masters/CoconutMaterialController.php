<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CoconutMaterialController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\CoconutMaterial::class; }
    protected function tableName(): string    { return 'm_coconut_material'; }
    protected function resourceName(): string { return 'Coconut Material'; }
    protected function viewPrefix(): string   { return 'admin.masters.coconut_material'; }
    protected function routePrefix(): string  { return 'masters.coconut_material'; }

    protected function datatableColumns(): array
    {
        return [
            'material_code' => 'Code',
            'material_desc' => 'Description',
            'material_uom'  => 'UOM',
            'plant_code'    => 'Plant',
            'updated_at'    => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['material_code', 'material_desc', 'material_uom', 'plant_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['material_code'] ?? '');
        if (empty($code)) return null;
        return [
            'material_code' => strtoupper($code),
            'material_desc' => trim($row['material_desc'] ?? ''),
            'material_uom'  => trim($row['material_uom'] ?? ''),
            'plant_code'    => trim($row['plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['material_code'])) return 'Material Code is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EM_COCONUT_MATERIAL_OUT',
            'urn'     => 'ZEPMS_EM_MATERIAL_OUT',   // CI4 uses material URN with MATKL=TR011 filter
            'filters' => ['WERKS' => '{plant_code}', 'MATKL' => 'TR011'],
            'columns' => ['MATNR', 'MAKTX', 'MEINS', 'WERKS'],
            'mapping' => [
                'material_code' => 'MATNR',
                'material_desc' => 'MAKTX',
                'material_uom'  => 'MEINS',
                'plant_code'    => 'WERKS',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_coconut_material')->where('company_id', $this->companyId())
            ->orderBy('material_code')->get(['material_code', 'material_desc', 'material_uom']);
        return $this->jsonSuccess('OK', $rows);
    }
}
