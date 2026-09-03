<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WbsController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Wbs::class; }
    protected function tableName(): string    { return 'm_wbs'; }
    protected function resourceName(): string { return 'WBS'; }
    protected function viewPrefix(): string   { return 'admin.masters.wbs'; }
    protected function routePrefix(): string  { return 'masters.wbs'; }

    protected function datatableColumns(): array
    {
        return [
            'wbs_code'        => 'WBS Code',
            'wbs_name'        => 'WBS Name',
            'wbs_group_code'  => 'Group Code',
            'wbs_group_name'  => 'Group Name',
            'updated_at'      => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['wbs_code', 'wbs_name', 'wbs_group_code', 'wbs_group_name'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['wbs_code'] ?? '');
        if (empty($code)) return null;
        return [
            'wbs_code'       => strtoupper($code),
            'wbs_name'       => trim($row['wbs_name'] ?? ''),
            'wbs_group_code' => trim($row['wbs_group_code'] ?? ''),
            'wbs_group_name' => trim($row['wbs_group_name'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['wbs_code'])) return 'WBS Code is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_WBS_OUT',
            'urn'     => 'ZEPMS_WBS_OUT',
            'filters' => ['P_WERKS' => '{plant_code}'],
            'columns' => ['POSID', 'POST1', 'GRPID', 'GRPDS'],
            'mapping' => [
                'wbs_code'       => 'POSID',
                'wbs_name'       => 'POST1',
                'wbs_group_code' => 'GRPID',
                'wbs_group_name' => 'GRPDS',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_wbs')->where('company_id', $this->companyId())
            ->orderBy('wbs_code')->get(['wbs_code', 'wbs_name']);
        return $this->jsonSuccess('OK', $rows);
    }
}
