<?php

namespace App\Http\Controllers\Admin\Masters;

class HarvestMethodController extends BaseGlobalLookupController
{
    protected function tableName(): string    { return 'm_harvest_method'; }
    protected function resourceName(): string { return 'Harvest Method'; }
    protected function primaryKey(): string   { return 'mhm_id'; }
    protected function viewPrefix(): string   { return 'admin.masters.global.harvest_method'; }
    protected function routePrefix(): string  { return 'masters.global.harvest_method'; }

    protected function datatableColumns(): array
    {
        return [
            'mhm_indicator'        => 'Indicator',
            'mhm_abbreviation'     => 'Abbreviation',
            'mhm_description'      => 'Description',
            'mhm_order_number_flag'=> 'Order No Flag',
            'updated_at'           => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['mhm_indicator', 'mhm_abbreviation', 'mhm_description', 'mhm_order_number_flag'];
    }

    protected function formFields(): array
    {
        return [
            'mhm_indicator'        => 'required|string|max:1',
            'mhm_abbreviation'     => 'required|string|max:15',
            'mhm_description'      => 'required|string|max:255',
            'mhm_order_number_flag'=> 'nullable|string|max:1',
        ];
    }

    protected function mapRow(array $data): array
    {
        return [
            'mhm_indicator'         => strtoupper(trim($data['mhm_indicator'])),
            'mhm_abbreviation'      => trim($data['mhm_abbreviation']),
            'mhm_description'       => trim($data['mhm_description']),
            'mhm_order_number_flag' => $data['mhm_order_number_flag'] ?? 'N',
        ];
    }
}
