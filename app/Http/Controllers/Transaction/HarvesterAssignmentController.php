<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\HarvesterAssignment;
use Illuminate\Http\Request;

/**
 * Harvester Assignment entry (t_harvester_assignment) — Estate Staff CRUD.
 * Assigns a harvester employee to a block/TPH for a date (palm harvesting).
 */
class HarvesterAssignmentController extends BaseTransactionController
{
    protected function modelClass(): string   { return HarvesterAssignment::class; }
    protected function dateColumn(): string    { return 'assignment_date'; }
    protected function viewPrefix(): string    { return 'transaction.harvester_assignment'; }
    protected function routePrefix(): string   { return 'transactions.harvester_assignment'; }
    protected function title(): string         { return 'Harvester Assignment'; }

    protected function datatableColumns(): array
    {
        return [
            'assignment_date'         => 'Date',
            'division_code'           => 'Division',
            'block_code'              => 'Block',
            'tph_code'                => 'TPH',
            'harvester_employee_name' => 'Harvester',
            'mandor_employee_name'    => 'Mandor',
        ];
    }

    protected function rules(Request $request): array
    {
        return [
            'assignment_date'         => 'required|date',
            'division_code'           => 'required|string|max:50',
            'block_code'              => 'required|string|max:50',
            'tph_code'                => 'nullable|string|max:50',
            'harvester_employee_code' => 'required|string|max:100',
            'mandor_employee_code'    => 'nullable|string|max:100',
        ];
    }

    protected function mapRow(Request $request): array
    {
        $harvester = \App\Models\Master\Employee::where('employee_code', $request->harvester_employee_code)->first();
        $mandor    = $request->mandor_employee_code
            ? \App\Models\Master\Employee::where('employee_code', $request->mandor_employee_code)->first()
            : null;

        return [
            'assignment_date'         => $request->assignment_date,
            'estate_code'             => $this->estateCode(),
            'division_code'           => trim($request->division_code),
            'block_code'              => trim($request->block_code),
            'tph_code'                => $request->tph_code ?: null,
            'harvester_employee_code' => trim($request->harvester_employee_code),
            'harvester_employee_name' => $harvester?->employee_name ?? '',
            'mandor_employee_code'    => $request->mandor_employee_code ?: null,
            'mandor_employee_name'    => $mandor?->employee_name,
        ];
    }

    protected function formData(): array
    {
        return [
            'divisions' => $this->divisions(),
            'blocks'    => $this->blocks(),
            'employees' => $this->employees(),
        ];
    }
}
