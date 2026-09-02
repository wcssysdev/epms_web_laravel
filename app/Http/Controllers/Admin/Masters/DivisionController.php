<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Models\Master\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class DivisionController extends BaseMasterController
{
    protected function modelClass(): string   { return Division::class; }
    protected function tableName(): string    { return 'm_division'; }
    protected function resourceName(): string { return 'Division'; }
    protected function viewPrefix(): string   { return 'admin.masters.division'; }
    protected function routePrefix(): string  { return 'masters.division'; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'   => 'Estate Code',
            'division_code' => 'Division Code',
            'division_name' => 'Division Name',
            'valid_from'    => 'Valid From',
            'valid_to'      => 'Valid To',
            'updated_at'    => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['estate_code', 'division_code', 'division_name', 'valid_from', 'valid_to'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['division_code'] ?? '');
        if (empty($code)) return null;

        return [
            'estate_code'   => strtoupper(trim($row['estate_code'] ?? '')),
            'division_code' => strtoupper($code),
            'division_name' => trim($row['division_name'] ?? ''),
            'valid_from'    => $this->parseDate($row['valid_from'] ?? ''),
            'valid_to'      => $this->parseDate($row['valid_to'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['estate_code']))   return 'Estate Code is required.';
        if (empty($row['division_code'])) return 'Division Code is required.';
        if (empty($row['division_name'])) return 'Division Name is required.';
        return null;
    }

    // Override getDatatable to add filter by estate
    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('estate_code')->orderBy('division_code');

        if ($request->filled('estate_code')) {
            $query->where('estate_code', $request->estate_code);
        }

        return DataTables::queryBuilder($query)->addIndexColumn()->make(true);
    }

    // Lookup: divisions by estate
    public function getByEstate(string $estateCode): JsonResponse
    {
        $divisions = DB::table('m_division')
            ->where('company_id', $this->companyId())
            ->where('estate_code', $estateCode)
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
            })
            ->orderBy('division_code')
            ->get(['division_code', 'division_name']);

        return $this->jsonSuccess('OK', $divisions);
    }

    protected function fetchFromSap(): array
    {
        $config = $this->companyConfig;
        if (!$config?->sap_api_url) return [];

        // Placeholder — implement per project SAP endpoint
        return [];
    }

    private function parseDate(string $val): ?string
    {
        if (empty($val)) return null;
        try {
            return \Carbon\Carbon::parse($val)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }
}
