<?php

namespace App\Http\Controllers\Admin\Masters;

class WorktypeController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Worktype::class; }
    protected function tableName(): string    { return 'm_worktype'; }
    protected function resourceName(): string { return 'Worktype'; }
    protected function viewPrefix(): string   { return 'admin.masters.worktype'; }
    protected function routePrefix(): string  { return 'masters.worktype'; }

    protected function datatableColumns(): array
    {
        return ['worktype_code' => 'Code', 'worktype_name' => 'Name', 'updated_at' => 'Last Updated'];
    }

    protected function csvHeaders(): array
    {
        return ['worktype_code', 'worktype_name'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['worktype_code'] ?? '');
        if (empty($code)) return null;
        return ['worktype_code' => strtoupper($code), 'worktype_name' => trim($row['worktype_name'] ?? '')];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['worktype_code'])) return 'Worktype Code is required.';
        return null;
    }

    // ── SAP two-step config ───────────────────────────────────────────────────
    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_PM_WORKTYPE_OUT',
            'urn'     => 'ZEPMS_PM_WORKTYPE_OUT',
            'filters' => ['BUKRS' => '*', 'LAND1' => '{country_code}'],
            'columns' => ['BUKRS', 'AUART', 'BEZEI'],
            'mapping' => [
                'worktype_code' => 'AUART',
                'worktype_name' => 'BEZEI',
            ],
        ];
    }
}
