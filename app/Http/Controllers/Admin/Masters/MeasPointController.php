<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MeasPointController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\MeasPoint::class; }
    protected function tableName(): string    { return 'm_meas_point'; }
    protected function resourceName(): string { return 'Measurement Point'; }
    protected function viewPrefix(): string   { return 'admin.masters.meas_point'; }
    protected function routePrefix(): string  { return 'masters.meas_point'; }

    protected function datatableColumns(): array
    {
        return [
            'point'            => 'Point',
            'equipment_code'   => 'Equipment Code',
            'vra_order_number' => 'Order Number',
            'unit'             => 'Unit',
            'description'      => 'Description',
            'plant_code'       => 'Plant',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['point', 'equipment_code', 'equipment_object_number', 'vra_order_number', 'unit', 'description', 'plant_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $point = trim($row['point'] ?? '');
        if (empty($point)) return null;
        return [
            'point'                   => $point,
            'equipment_code'          => trim($row['equipment_code'] ?? ''),
            'equipment_object_number' => trim($row['equipment_object_number'] ?? ''),
            'vra_order_number'        => trim($row['vra_order_number'] ?? ''),
            'unit'                    => trim($row['unit'] ?? ''),
            'description'             => trim($row['description'] ?? ''),
            'plant_code'              => trim($row['plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['point'])) return 'Measurement Point is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_PM_MEASPOINT_OUT',
            'urn'     => 'ZEPMS_PM_MEASPOINT_OUT',
            'filters' => ['LAND1' => '{country_code}'],
            'columns' => ['BUKRS', 'WERKS', 'AUFNR', 'EQUNR', 'OBJNR', 'POINT', 'UNITTXT', 'PTTXT'],
            'mapping' => [
                'plant_code'              => 'WERKS',
                'vra_order_number'        => 'AUFNR',
                'equipment_code'          => 'EQUNR',
                'equipment_object_number' => 'OBJNR',
                'point'                   => 'POINT',
                'unit'                    => 'UNITTXT',
                'description'             => 'PTTXT',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_meas_point')->where('company_id', $this->companyId())
            ->orderBy('point')->get(['point', 'equipment_code', 'description']);
        return $this->jsonSuccess('OK', $rows);
    }
}
