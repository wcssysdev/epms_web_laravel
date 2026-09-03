<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use Illuminate\Http\Request;

/** Durian Variety — company-scoped CRUD. */
class VarietyController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_durian_variety'; }
    protected function resourceName(): string { return 'Durian Variety'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.variety'; }
    protected function routePrefix(): string  { return 'masters.durian.variety'; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'   => 'Estate',
            'division_code' => 'Division',
            'block_code'    => 'Block',
            'row_no'        => 'Row No',
            'variety'       => 'Variety',
            'updated_at'    => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'estate_code'   => 'required|string|max:50',
            'division_code' => 'required|string|max:50',
            'block_code'    => 'required|string|max:50',
            'row_no'        => 'nullable|integer',
            'variety'       => 'required|string|max:100',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'estate_code'   => strtoupper(trim($request->estate_code)),
            'division_code' => strtoupper(trim($request->division_code)),
            'block_code'    => strtoupper(trim($request->block_code)),
            'row_no'        => $request->filled('row_no') ? (int) $request->row_no : null,
            'variety'       => trim($request->variety),
        ];
    }
}
