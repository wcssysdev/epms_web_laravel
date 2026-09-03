<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use Illuminate\Http\Request;

/**
 * BIN master — simple company-scoped CRUD (code only). No SAP, no CSV.
 * Reuses the generic CRUD flow from BaseGroupingController.
 */
class BinController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_bin'; }
    protected function resourceName(): string { return 'BIN'; }
    protected function viewPrefix(): string   { return 'admin.masters.bin'; }
    protected function routePrefix(): string  { return 'masters.bin'; }

    protected function datatableColumns(): array
    {
        return [
            'bin_code'   => 'BIN Code',
            'updated_at' => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return ['bin_code' => 'required|string|max:100'];
    }

    protected function storeData(Request $request): array
    {
        return ['bin_code' => strtoupper(trim($request->bin_code))];
    }
}
