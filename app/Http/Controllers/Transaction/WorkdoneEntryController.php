<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Workdone;
use App\Models\Master\Activity;
use Illuminate\Http\Request;

/**
 * Work Completion entry (t_workdone) — Estate Staff operational CRUD.
 * Records completed activity work per employee/block for a date.
 */
class WorkdoneEntryController extends BaseTransactionController
{
    protected function modelClass(): string   { return Workdone::class; }
    protected function dateColumn(): string    { return 'workdone_date'; }
    protected function viewPrefix(): string    { return 'transaction.workdone'; }
    protected function routePrefix(): string   { return 'transactions.workdone'; }
    protected function title(): string         { return 'Work Completion'; }

    /** t_workdone.id is a non-incrementing varchar — generate one. */
    protected function generateId(): ?string
    {
        return 'WD' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);
    }

    protected function datatableColumns(): array
    {
        return [
            'workdone_date' => 'Date',
            'division_code' => 'Division',
            'block_code'    => 'Block',
            'activity_name' => 'Activity',
            'employee_name' => 'Employee',
            'qty'           => 'Qty',
            'activity_uom'  => 'UOM',
        ];
    }

    protected function rules(Request $request): array
    {
        return [
            'workdone_date' => 'required|date',
            'division_code' => 'required|string|max:50',
            'block_code'    => 'nullable|string|max:50',
            'activity_code' => 'required|string|max:100',
            'employee_code' => 'required|string|max:100',
            'qty'           => 'required|numeric|min:0',
            'target_qty'    => 'nullable|numeric|min:0',
            'description'   => 'nullable|string|max:255',
        ];
    }

    protected function mapRow(Request $request): array
    {
        $emp      = \App\Models\Master\Employee::where('employee_code', $request->employee_code)->first();
        $activity = Activity::where('activity_code', $request->activity_code)->first();

        return [
            'workdone_date' => $request->workdone_date,
            'estate_code'   => $this->estateCode(),
            'plant_code'    => $this->plantCode(),
            'division_code' => trim($request->division_code),
            'block_code'    => $request->block_code ?: null,
            'activity_code' => trim($request->activity_code),
            'activity_name' => $activity?->activity_name ?? '',
            'activity_uom'  => $activity?->activity_uom ?? null,
            'employee_code' => trim($request->employee_code),
            'employee_name' => $emp?->employee_name ?? '',
            'qty'           => (float) $request->qty,
            'target_qty'    => $request->target_qty !== null ? (float) $request->target_qty : null,
            'description'   => $request->description ?: null,
            'is_planned'    => false,
        ];
    }

    protected function formData(): array
    {
        return [
            'divisions'  => $this->divisions(),
            'blocks'     => $this->blocks(),
            'employees'  => $this->employees(),
            'activities' => Activity::orderBy('activity_code')
                                ->get(['activity_code', 'activity_name', 'activity_uom']),
        ];
    }
}
