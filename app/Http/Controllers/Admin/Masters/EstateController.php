<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Models\Master\Estate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EstateController extends BaseMasterController
{
    protected function modelClass(): string   { return Estate::class; }
    protected function tableName(): string    { return 'm_estate'; }
    protected function resourceName(): string { return 'Estate'; }
    protected function viewPrefix(): string   { return 'admin.masters.estate'; }
    protected function routePrefix(): string  { return 'masters.estate'; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'       => 'Estate Code',
            'estate_name'       => 'Estate Name',
            'estate_plant_code' => 'Plant Code',
            'created_by'        => 'Created By',
            'updated_at'        => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['estate_code', 'estate_name', 'estate_plant_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $estateCode = trim($row['estate_code'] ?? '');
        if (empty($estateCode)) return null;

        return [
            'estate_code'       => strtoupper($estateCode),
            'estate_name'       => trim($row['estate_name'] ?? ''),
            'estate_plant_code' => trim($row['estate_plant_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['estate_code'])) return 'Estate Code is required.';
        if (empty($row['estate_name'])) return 'Estate Name is required.';
        return null;
    }

    // SAP sync
    protected function fetchFromSap(): array
    {
        $config = $this->companyConfig;
        if (!$config?->sap_api_url) return [];

        try {
            helper('sap');
            $result = get_master_data_from_sap_laravel(
                $config,
                '{"urn:ZEPMS_EM_ESTATE_OUT": {"BUKRS": "' . $this->companyCode() . '"}}'
            );

            return collect($result)->map(fn($r) => [
                'estate_code'       => $r['ESTNR']  ?? '',
                'estate_name'       => $r['NAME1']  ?? '',
                'estate_plant_code' => $r['WERKS']  ?? '',
            ])->filter(fn($r) => !empty($r['estate_code']))->values()->toArray();

        } catch (\Exception $e) {
            throw new \RuntimeException('SAP fetch failed: ' . $e->getMessage());
        }
    }

    // Lookup endpoint for other master data dropdowns
    public function lookup(): JsonResponse
    {
        $estates = DB::table('m_estate')
            ->where('company_id', $this->companyId())
            ->orderBy('estate_code')
            ->get(['estate_code', 'estate_name']);

        return $this->jsonSuccess('OK', $estates);
    }
}
