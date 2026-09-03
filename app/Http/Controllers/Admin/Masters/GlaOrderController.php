<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GL Account (Order) — CSV/CRUD only in CI4 (no SAP sync).
 */
class GlaOrderController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\GlaOrder::class; }
    protected function tableName(): string    { return 'm_glacc_gi_order'; }
    protected function resourceName(): string { return 'GL Account Order'; }
    protected function viewPrefix(): string   { return 'admin.masters.gla_order'; }
    protected function routePrefix(): string  { return 'masters.gla_order'; }

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

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_glacc_gi_order')->where('company_id', $this->companyId())
            ->orderBy('account_number')->get(['account_number', 'account_desc']);
        return $this->jsonSuccess('OK', $rows);
    }
}
