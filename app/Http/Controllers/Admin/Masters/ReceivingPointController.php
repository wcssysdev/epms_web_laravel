<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReceivingPointController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\ReceivingPoint::class; }
    protected function tableName(): string    { return 'm_receiving_point'; }
    protected function resourceName(): string { return 'Ramp'; }
    protected function viewPrefix(): string   { return 'admin.masters.receiving_point'; }
    protected function routePrefix(): string  { return 'masters.receiving_point'; }

    protected function datatableColumns(): array
    {
        return [
            'receiving_point_code' => 'Receiving Point Code',
            'updated_at'           => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['receiving_point_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['receiving_point_code'] ?? '');
        if (empty($code)) return null;
        return ['receiving_point_code' => strtoupper($code)];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['receiving_point_code'])) return 'Receiving Point Code is required.';
        return null;
    }

    protected function sapConfig(): ?array
    {
        return [
            'staging' => 'ZEPMS_EM_RECEIVING_OUT',
            'urn'     => 'ZEPMS_EM_RECEIVING_OUT',
            'filters' => [],
            'columns' => ['VALUE'],
            'mapping' => ['receiving_point_code' => 'VALUE'],
        ];
    }

    public function lookup(): JsonResponse
    {
        $rows = DB::table('m_receiving_point')->where('company_id', $this->companyId())
            ->orderBy('receiving_point_code')->get(['receiving_point_code']);
        return $this->jsonSuccess('OK', $rows);
    }
}
