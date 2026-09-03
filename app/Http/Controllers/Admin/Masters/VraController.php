<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VraController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Vra::class; }
    protected function tableName(): string    { return 'm_vra'; }
    protected function resourceName(): string { return 'License Number'; }
    protected function viewPrefix(): string   { return 'admin.masters.vra'; }
    protected function routePrefix(): string  { return 'masters.vra'; }

    protected function datatableColumns(): array
    {
        return [
            'license_number'   => 'License Number',
            'equipment_code'   => 'Equipment Code',
            'object_type'      => 'Object Type',
            'vra_order_number' => 'Order Number',
            'plant_code'       => 'Plant',
            'valid_from'       => 'Valid From',
            'valid_to'         => 'Valid To',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['license_number', 'equipment_code', 'object_type', 'vra_order_number', 'plant_code', 'valid_from', 'valid_to'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['license_number'] ?? '');
        if (empty($code)) return null;
        return [
            'license_number'   => strtoupper($code),
            'equipment_code'   => trim($row['equipment_code'] ?? ''),
            'object_type'      => trim($row['object_type'] ?? ''),
            'vra_order_number' => trim($row['vra_order_number'] ?? ''),
            'plant_code'       => trim($row['plant_code'] ?? ''),
            'valid_from'       => $this->parseDate($row['valid_from'] ?? ''),
            'valid_to'         => $this->parseDate($row['valid_to'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['license_number'])) return 'License Number is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EM_VRA_OUT',
            'urn'     => 'ZEPMS_EM_VRA_OUT',
            'filters' => ['WERKS' => '{plant_code}'],
            'columns' => ['LICENSE_NUM', 'AUFNR', 'EQUNR', 'EQART', 'WERKS', 'DATAB', 'DATBI'],
            'mapping' => [
                'license_number'   => 'LICENSE_NUM',
                'vra_order_number' => 'AUFNR',
                'equipment_code'   => 'EQUNR',
                'object_type'      => 'EQART',
                'plant_code'       => 'WERKS',
            ],
        ];
    }

    protected function transformSapRow(array $master, array $staging): array
    {
        $master['valid_from'] = $this->parseDate($staging['DATAB'] ?? '');
        $master['valid_to']   = $this->parseDate($staging['DATBI'] ?? '');
        return $master;
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_vra')->where('company_id', $this->companyId())
            ->orderBy('license_number')->get(['license_number', 'equipment_code', 'vra_order_number']);
        return $this->jsonSuccess('OK', $rows);
    }

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') return null;
        $val = str_replace('.', '-', $val);
        try { return \Carbon\Carbon::parse($val)->toDateString(); } catch (\Exception $e) { return null; }
    }
}
