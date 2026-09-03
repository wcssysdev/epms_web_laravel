<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SlocController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Sloc::class; }
    protected function tableName(): string    { return 'm_sloc'; }
    protected function resourceName(): string { return 'Storage Location'; }
    protected function viewPrefix(): string   { return 'admin.masters.sloc'; }
    protected function routePrefix(): string  { return 'masters.sloc'; }

    protected function datatableColumns(): array
    {
        return [
            'sloc_code'  => 'SLoc Code',
            'sloc_desc'  => 'Description',
            'plant_code' => 'Plant Code',
            'updated_at' => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['sloc_code', 'sloc_desc', 'plant_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['sloc_code'] ?? '');
        if (empty($code)) return null;
        return [
            'sloc_code'  => strtoupper($code),
            'sloc_desc'  => trim($row['sloc_desc'] ?? ''),
            'plant_code' => trim($row['plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['sloc_code'])) return 'SLoc Code is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_STORLOC_OUT',
            'urn'     => 'ZEPMS_STORLOC_OUT',
            'filters' => ['WERKS' => '{plant_code}'],
            'columns' => ['LGORT', 'LGOBE', 'WERKS'],
            'mapping' => [
                'sloc_code'  => 'LGORT',
                'sloc_desc'  => 'LGOBE',
                'plant_code' => 'WERKS',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $slocs = DB::table('m_sloc')->where('company_id', $this->companyId())
            ->orderBy('sloc_code')->get(['sloc_code', 'sloc_desc']);
        return $this->jsonSuccess('OK', $slocs);
    }
}
