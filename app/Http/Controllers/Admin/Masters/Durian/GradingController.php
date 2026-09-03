<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use Illuminate\Http\Request;

/** Durian Grading — company-scoped CRUD. */
class GradingController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_durian_grading'; }
    protected function resourceName(): string { return 'Durian Grading'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.grading'; }
    protected function routePrefix(): string  { return 'masters.durian.grading'; }

    protected function datatableColumns(): array
    {
        return [
            'crop_type'       => 'Crop Type',
            'type_of_variety' => 'Variety Type',
            'grading_code'    => 'Grading Code',
            'grading_weight'  => 'Weight',
            'updated_at'      => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'crop_type'       => 'nullable|string|max:100',
            'type_of_variety' => 'nullable|string|max:100',
            'grading_code'    => 'required|string|max:100',
            'grading_weight'  => 'nullable|string|max:100',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'crop_type'       => trim((string) $request->crop_type),
            'type_of_variety' => trim((string) $request->type_of_variety),
            'grading_code'    => strtoupper(trim($request->grading_code)),
            'grading_weight'  => trim((string) $request->grading_weight),
        ];
    }
}
