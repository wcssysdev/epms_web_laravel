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
}
