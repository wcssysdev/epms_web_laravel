<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use Illuminate\Http\Request;

/**
 * Confirmation Text master — company-scoped CRUD. No SAP.
 */
class ConfirmationTextController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_confirmation_text'; }
    protected function resourceName(): string { return 'Confirmation Text'; }
    protected function viewPrefix(): string   { return 'admin.masters.confirmation_text'; }
    protected function routePrefix(): string  { return 'masters.confirmation_text'; }

    protected function datatableColumns(): array
    {
        return [
            'ctext_code' => 'Code',
            'ctext_text' => 'Text',
            'ctext_desc' => 'Description',
            'updated_at' => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'ctext_code' => 'required|string|max:100',
            'ctext_text' => 'required|string|max:255',
            'ctext_desc' => 'nullable|string|max:255',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'ctext_code' => strtoupper(trim($request->ctext_code)),
            'ctext_text' => trim($request->ctext_text),
            'ctext_desc' => trim((string) $request->ctext_desc),
        ];
    }
}
