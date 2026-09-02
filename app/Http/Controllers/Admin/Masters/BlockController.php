<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Models\Master\Block;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BlockController extends BaseMasterController
{
    protected function modelClass(): string   { return Block::class; }
    protected function tableName(): string    { return 'm_block'; }
    protected function resourceName(): string { return 'Block'; }
    protected function viewPrefix(): string   { return 'admin.masters.block'; }
    protected function routePrefix(): string  { return 'masters.block'; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'    => 'Estate',
            'division_code'  => 'Division',
            'block_code'     => 'Block Code',
            'block_name'     => 'Block Name',
            'crop_type'      => 'Crop Type',
            'block_hectarage'=> 'Hectarage',
            'total_palm'     => 'Total Palm',
            'valid_from'     => 'Valid From',
            'valid_to'       => 'Valid To',
        ];
    }

    protected function csvHeaders(): array
    {
        return [
            'estate_code', 'division_code', 'block_code', 'block_name',
            'crop_type', 'block_hectarage', 'block_planted_date',
            'valid_from', 'valid_to', 'block_state', 'total_palm',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['block_code'] ?? '');
        if (empty($code)) return null;

        return [
            'estate_code'        => strtoupper(trim($row['estate_code'] ?? '')),
            'division_code'      => strtoupper(trim($row['division_code'] ?? '')),
            'block_code'         => strtoupper($code),
            'block_name'         => trim($row['block_name'] ?? ''),
            'crop_type'          => strtoupper(trim($row['crop_type'] ?? 'PALM')),
            'block_hectarage'    => is_numeric($row['block_hectarage'] ?? '') ? (float) $row['block_hectarage'] : null,
            'block_planted_date' => $this->parseDate($row['block_planted_date'] ?? ''),
            'valid_from'         => $this->parseDate($row['valid_from'] ?? ''),
            'valid_to'           => $this->parseDate($row['valid_to'] ?? ''),
            'block_state'        => trim($row['block_state'] ?? ''),
            'is_planted'         => false,
            'total_palm'         => (int) ($row['total_palm'] ?? 0),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['estate_code']))   return 'Estate Code is required.';
        if (empty($row['division_code'])) return 'Division Code is required.';
        if (empty($row['block_code']))    return 'Block Code is required.';
        return null;
    }

    public function getDatatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery()->orderBy('estate_code')->orderBy('division_code')->orderBy('block_code');

        if ($request->filled('estate_code'))   $query->where('estate_code', $request->estate_code);
        if ($request->filled('division_code')) $query->where('division_code', $request->division_code);
        if ($request->filled('crop_type'))     $query->where('crop_type', $request->crop_type);

        return DataTables::queryBuilder($query)->addIndexColumn()->make(true);
    }

    // Lookup: blocks by division
    public function getByDivision(string $estateCode, string $divisionCode): JsonResponse
    {
        $blocks = DB::table('m_block')
            ->where('company_id', $this->companyId())
            ->where('estate_code', $estateCode)
            ->where('division_code', $divisionCode)
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
            })
            ->orderBy('block_code')
            ->get(['block_code', 'block_name', 'crop_type', 'block_hectarage', 'total_palm']);

        return $this->jsonSuccess('OK', $blocks);
    }

    private function parseDate(string $val): ?string
    {
        if (empty($val)) return null;
        try { return \Carbon\Carbon::parse($val)->toDateString(); }
        catch (\Exception $e) { return null; }
    }
}
