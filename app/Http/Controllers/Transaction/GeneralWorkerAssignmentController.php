<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\GeneralWorkerAssignment;
use App\Models\Master\Activity;
use Illuminate\Http\Request;

/**
 * General Worker Assignment entry (t_general_worker_assignment) — Estate Staff CRUD.
 * The cost object (block vs order number) depends on the selected activity's
 * cost flags (cost_by_block / cost_by_order_number).
 */
class GeneralWorkerAssignmentController extends BaseTransactionController
{
    protected function modelClass(): string   { return GeneralWorkerAssignment::class; }
    protected function dateColumn(): string    { return 'assignment_date'; }
    protected function viewPrefix(): string    { return 'transaction.general_worker_assignment'; }
    protected function routePrefix(): string   { return 'transactions.general_worker_assignment'; }
    protected function title(): string         { return 'General Worker Assignment'; }

    protected function datatableColumns(): array
    {
        return [
            'assignment_date'      => 'Date',
            'division_code'        => 'Division',
            'activity_name'        => 'Activity',
            'block_code'           => 'Block',
            'order_number'         => 'Order No.',
            'worker_employee_name' => 'Worker',
            'mandor_employee_name' => 'Mandor',
        ];
    }

    protected function rules(Request $request): array
    {
        $rules = [
            'assignment_date'      => 'required|date',
            'division_code'        => 'required|string|max:50',
            'activity_code'        => 'required|string|max:100',
            'worker_employee_code' => 'required|string|max:100',
            'mandor_employee_code' => 'nullable|string|max:100',
            'block_code'           => 'nullable|string|max:50',
            'order_number'         => 'nullable|string|max:100',
        ];

        // Conditionally require the cost object matching the activity's flags.
        $activity = Activity::where('activity_code', $request->activity_code)->first();
        if ($activity) {
            if ($activity->cost_by_block)        $rules['block_code']   = 'required|string|max:50';
            if ($activity->cost_by_order_number) $rules['order_number'] = 'required|string|max:100';
        }

        return $rules;
    }

    protected function mapRow(Request $request): array
    {
        $worker   = \App\Models\Master\Employee::where('employee_code', $request->worker_employee_code)->first();
        $mandor   = $request->mandor_employee_code
            ? \App\Models\Master\Employee::where('employee_code', $request->mandor_employee_code)->first()
            : null;
        $activity = Activity::where('activity_code', $request->activity_code)->first();

        return [
            'assignment_date'      => $request->assignment_date,
            'estate_code'          => $this->estateCode(),
            'division_code'        => trim($request->division_code),
            'activity_code'        => trim($request->activity_code),
            'activity_name'        => $activity?->activity_name ?? '',
            'block_code'           => $request->block_code ?: null,
            'order_number'         => $request->order_number ?: null,
            'worker_employee_code' => trim($request->worker_employee_code),
            'worker_employee_name' => $worker?->employee_name ?? '',
            'mandor_employee_code' => $request->mandor_employee_code ?: null,
            'mandor_employee_name' => $mandor?->employee_name,
        ];
    }

    protected function formData(): array
    {
        return [
            'divisions'  => $this->divisions(),
            'blocks'     => $this->blocks(),
            'employees'  => $this->employees(),
            'activities' => Activity::orderBy('activity_code')->get([
                'activity_code', 'activity_name',
                'cost_by_block', 'cost_by_order_number',
            ]),
        ];
    }
}
