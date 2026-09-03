<?php

namespace App\Http\Controllers\Admin\Masters;

class ActivityController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Activity::class; }
    protected function tableName(): string    { return 'm_activity'; }
    protected function resourceName(): string { return 'Activity'; }
    protected function viewPrefix(): string   { return 'admin.masters.activity'; }
    protected function routePrefix(): string  { return 'masters.activity'; }

    protected function datatableColumns(): array
    {
        return [
            'activity_code'       => 'Code',
            'activity_name'       => 'Name',
            'activity_uom'        => 'UOM',
            'activity_group_code' => 'Group',
            'updated_at'          => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return [
            'activity_code', 'activity_name', 'activity_uom', 'activity_uom_name',
            'activity_group_code', 'activity_cost_by_block', 'activity_cost_by_auc',
            'activity_cost_by_order_number', 'activity_cost_by_cost_center',
            'activity_block_is_lc', 'activity_block_is_immature',
            'activity_block_is_mature', 'activity_block_is_scout',
            'activity_is_wbs_required',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['activity_code'] ?? '');
        if (empty($code)) return null;
        return [
            'activity_code'                  => strtoupper($code),
            'activity_name'                  => trim($row['activity_name'] ?? ''),
            'activity_uom'                   => trim($row['activity_uom'] ?? ''),
            'activity_uom_name'              => trim($row['activity_uom_name'] ?? ''),
            'activity_group_code'            => trim($row['activity_group_code'] ?? ''),
            'cost_by_block'                  => (bool)($row['activity_cost_by_block'] ?? false),
            'cost_by_auc'                    => (bool)($row['activity_cost_by_auc'] ?? false),
            'cost_by_order_number'           => (bool)($row['activity_cost_by_order_number'] ?? false),
            'cost_by_cost_center'            => (bool)($row['activity_cost_by_cost_center'] ?? false),
            'block_is_lc'                    => (bool)($row['activity_block_is_lc'] ?? false),
            'block_is_immature'              => (bool)($row['activity_block_is_immature'] ?? false),
            'block_is_mature'                => (bool)($row['activity_block_is_mature'] ?? true),
            'block_is_scout'                 => (bool)($row['activity_block_is_scout'] ?? false),
            'is_wbs_required'                => (bool)($row['activity_is_wbs_required'] ?? false),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['activity_code'])) return 'Activity Code is required.';
        if (empty($row['activity_name'])) return 'Activity Name is required.';
        return null;
    }

    // ── SAP two-step config ───────────────────────────────────────────────────
    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_ACTIVITY_OUT',
            'urn'     => 'ZEPMS_ACTIVITY_OUT',
            'filters' => [],   // CI4 sends no filters for activity
            'columns' => ['ACTVT_NO', 'ACTVT_NAME', 'AMEIN', 'AMEIN2', 'BLOCK', 'COST_CENTER',
                          'AUC', 'ORDER_NUMBER', 'BLOCK_LC', 'BLOCK_IMMATURE', 'BLOCK_SCOUT',
                          'BLOCK_MATURE', 'WRK_GRP', 'DTWBS'],
            'mapping' => [
                'activity_code'      => 'ACTVT_NO',
                'activity_name'      => 'ACTVT_NAME',
                'activity_uom_name'  => 'AMEIN',
                'activity_uom'       => 'AMEIN2',
                // booleans + group handled in transformSapRow
            ],
        ];
    }

    protected function transformSapRow(array $master, array $staging): array
    {
        $x = fn ($v) => strtoupper(trim((string) $v)) === 'X';

        $master['cost_by_block']        = $x($staging['BLOCK'] ?? '');
        $master['cost_by_cost_center']  = $x($staging['COST_CENTER'] ?? '');
        $master['cost_by_auc']          = $x($staging['AUC'] ?? '');
        $master['cost_by_order_number'] = $x($staging['ORDER_NUMBER'] ?? '');
        $master['block_is_lc']          = $x($staging['BLOCK_LC'] ?? '');
        $master['block_is_immature']    = $x($staging['BLOCK_IMMATURE'] ?? '');
        $master['block_is_scout']       = $x($staging['BLOCK_SCOUT'] ?? '');
        $master['block_is_mature']      = $x($staging['BLOCK_MATURE'] ?? '');
        $master['is_wbs_required']      = $x($staging['DTWBS'] ?? '');
        $master['activity_group_code']  = trim((string) ($staging['WRK_GRP'] ?? '')) ?: 'BLANK';

        return $master;
    }
}
