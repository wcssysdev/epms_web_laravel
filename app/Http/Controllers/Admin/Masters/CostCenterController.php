<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CostCenterController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\CostCenter::class; }
    protected function tableName(): string    { return 'm_cost_center'; }
    protected function resourceName(): string { return 'Cost Center'; }
    protected function viewPrefix(): string   { return 'admin.masters.cost_center'; }
    protected function routePrefix(): string  { return 'masters.cost_center'; }

    protected function datatableColumns(): array
    {
        return [
            'cc_code'    => 'CC Code',
            'cc_desc'    => 'Description',
            'cc_gsber'   => 'Business Area',
            'valid_from' => 'Valid From',
            'valid_to'   => 'Valid To',
            'updated_at' => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['cc_code', 'cc_desc', 'cc_gsber', 'valid_from', 'valid_to'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['cc_code'] ?? '');
        if (empty($code)) return null;
        return [
            'cc_code'    => strtoupper($code),
            'cc_desc'    => trim($row['cc_desc'] ?? ''),
            'cc_gsber'   => trim($row['cc_gsber'] ?? ''),
            'valid_from' => $this->parseDate($row['valid_from'] ?? ''),
            'valid_to'   => $this->parseDate($row['valid_to'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['cc_code'])) return 'Cost Center Code is required.';
        return null;
    }

    public function lookup(): JsonResponse
    {
        $ccs = DB::table('m_cost_center')->where('company_id', $this->companyId())
            ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()))
            ->orderBy('cc_code')->get(['cc_code', 'cc_desc']);
        return $this->jsonSuccess('OK', $ccs);
    }

    // ── SAP two-step config ───────────────────────────────────────────────────
    // Note: SAP URN differs from the staging table name (matches CI4).
    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EM_COST_CENTER_OUT',
            'urn'     => 'ZEPMS_COSTCENTER_OUT',
            'filters' => ['WERKS' => '{plant_code}'],
            'columns' => ['KOSTL', 'LTEXT', 'BUKRS', 'GSBER', 'DATAB', 'DATBI'],
            'mapping' => [
                'cc_code'  => 'KOSTL',
                'cc_desc'  => 'LTEXT',
                'cc_gsber' => 'GSBER',
                // dates handled in transformSapRow
            ],
        ];
    }

    protected function transformSapRow(array $master, array $staging): array
    {
        $master['valid_from'] = $this->parseDate($staging['DATAB'] ?? '');
        $master['valid_to']   = $this->parseDate($staging['DATBI'] ?? '');
        return $master;
    }

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') return null;
        $val = str_replace('.', '-', $val); // SAP dotted dates
        try { return \Carbon\Carbon::parse($val)->toDateString(); } catch (\Exception $e) { return null; }
    }
}
