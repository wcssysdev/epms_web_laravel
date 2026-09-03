<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GlAccountController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\GlAccount::class; }
    protected function tableName(): string    { return 'm_glacc'; }
    protected function resourceName(): string { return 'GL Account'; }
    protected function viewPrefix(): string   { return 'admin.masters.gl_account'; }
    protected function routePrefix(): string  { return 'masters.gl_account'; }

    protected function datatableColumns(): array
    {
        return [
            'account_number' => 'Account Number',
            'account_desc'   => 'Description',
            'updated_at'     => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['account_number', 'account_desc'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['account_number'] ?? '');
        if (empty($code)) return null;
        return [
            'account_number' => strtoupper($code),
            'account_desc'   => trim($row['account_desc'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['account_number'])) return 'Account Number is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_GL_OUT',
            'urn'     => 'ZEPMS_GL_OUT',
            'filters' => ['WERKS' => '{plant_code}'],
            'columns' => ['SAKNR', 'TXT50', 'BUKRS'],
            'mapping' => [
                'account_number' => 'SAKNR',
                'account_desc'   => 'TXT50',
            ],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_glacc')->where('company_id', $this->companyId())
            ->orderBy('account_number')->get(['account_number', 'account_desc']);
        return $this->jsonSuccess('OK', $rows);
    }
}
