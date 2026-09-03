<?php

namespace App\Http\Controllers\Admin\Masters\Durian;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use Illuminate\Http\Request;

/** Durian Task — company-scoped CRUD. */
class TaskController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_durian_task'; }
    protected function resourceName(): string { return 'Durian Task'; }
    protected function viewPrefix(): string   { return 'admin.masters.durian.task'; }
    protected function routePrefix(): string  { return 'masters.durian.task'; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'   => 'Estate',
            'division_code' => 'Division',
            'block_code'    => 'Block',
            'task_no'       => 'Task No',
            'row_no'        => 'Row No',
            'task_validity' => 'Validity',
            'updated_at'    => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'estate_code'   => 'required|string|max:50',
            'division_code' => 'required|string|max:50',
            'block_code'    => 'required|string|max:50',
            'task_no'       => 'required|string|max:100',
            'row_no'        => 'nullable|integer',
            'task_validity' => 'nullable|date',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'estate_code'   => strtoupper(trim($request->estate_code)),
            'division_code' => strtoupper(trim($request->division_code)),
            'block_code'    => strtoupper(trim($request->block_code)),
            'task_no'       => trim($request->task_no),
            'row_no'        => $request->filled('row_no') ? (int) $request->row_no : null,
            'task_validity' => $request->filled('task_validity') ? $request->task_validity : null,
        ];
    }
}
