<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DestinationController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Destination::class; }
    protected function tableName(): string    { return 'm_destination'; }
    protected function resourceName(): string { return 'Delivery Destination'; }
    protected function viewPrefix(): string   { return 'admin.masters.destination'; }
    protected function routePrefix(): string  { return 'masters.destination'; }

    protected function datatableColumns(): array
    {
        return [
            'destination_code' => 'Destination Code',
            'destination_name' => 'Destination Name',
            'updated_at'       => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['destination_code', 'destination_name'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['destination_code'] ?? '');
        if (empty($code)) return null;
        return [
            'destination_code' => strtoupper($code),
            'destination_name' => trim($row['destination_name'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['destination_code'])) return 'Destination Code is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EM_DESTINATION_OUT',
            'urn'     => 'ZEPMS_EM_DESTINATION_OUT',
            'filters' => ['ESTNR' => '{estate_code}', 'LAND1' => '{country_code}'],
            'columns' => ['WERKS', 'NAME1'],
            'mapping' => [
                'destination_code' => 'WERKS',
                'destination_name' => 'NAME1',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_destination')->where('company_id', $this->companyId())
            ->orderBy('destination_code')->get(['destination_code', 'destination_name']);
        return $this->jsonSuccess('OK', $rows);
    }
}
