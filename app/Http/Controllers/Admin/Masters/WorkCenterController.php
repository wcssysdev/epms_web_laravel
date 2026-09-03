<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WorkCenterController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\WorkCenter::class; }
    protected function tableName(): string    { return 'm_work_center'; }
    protected function resourceName(): string { return 'Work Center'; }
    protected function viewPrefix(): string   { return 'admin.masters.work_center'; }
    protected function routePrefix(): string  { return 'masters.work_center'; }

    protected function datatableColumns(): array
    {
        return [
            'work_center_code' => 'Code',
            'work_center_name' => 'Name',
            'estate_code'      => 'Estate',
            'division_code'    => 'Division',
            'plant_code'       => 'Plant',
            'updated_at'       => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['work_center_code', 'work_center_name', 'estate_code', 'division_code', 'plant_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['work_center_code'] ?? '');
        if (empty($code)) return null;
        return [
            'work_center_code' => strtoupper($code),
            'work_center_name' => trim($row['work_center_name'] ?? ''),
            'estate_code'      => strtoupper(trim($row['estate_code'] ?? '')),
            'division_code'    => strtoupper(trim($row['division_code'] ?? '')),
            'plant_code'       => trim($row['plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['work_center_code'])) return 'Work Center Code is required.';
        return null;
    }

    public function lookup(): JsonResponse
    {
        $wcs = DB::table('m_work_center')->where('company_id', $this->companyId())
            ->orderBy('work_center_code')->get(['work_center_code', 'work_center_name']);
        return $this->jsonSuccess('OK', $wcs);
    }

    // ── SAP two-step config ───────────────────────────────────────────────────
    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EM_WORK_CENTER_OUT',
            'urn'     => 'ZEPMS_EM_WORK_CENTER_OUT',
            'filters' => ['BUKRS' => '*', 'LAND1' => '{country_code}'],
            'columns' => ['BUKRS', 'WERKS', 'ESTNR', 'SPART', 'ARBPL', 'KTEXT'],
            'mapping' => [
                'plant_code'       => 'WERKS',
                'estate_code'      => 'ESTNR',
                'division_code'    => 'SPART',
                'work_center_code' => 'ARBPL',
                'work_center_name' => 'KTEXT',
            ],
        ];
    }
}
