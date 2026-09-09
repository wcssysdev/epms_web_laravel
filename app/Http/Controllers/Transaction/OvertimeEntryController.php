<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Overtime;
use App\Models\Master\Activity;
use App\Models\Master\Employee;
use App\Models\Master\Block;
use App\Models\Global\Uom;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Work Overtime entry (t_overtime) — Estate Staff role-4 operational CRUD.
 * Maps to CI4 Transactions/Overtime.php.
 */
class OvertimeEntryController extends BaseTransactionController
{
    protected function modelClass(): string  { return Overtime::class; }
    protected function dateColumn(): string  { return 'overtime_date'; }
    protected function viewPrefix(): string  { return 'transaction.overtime'; }
    protected function routePrefix(): string { return 'transactions.overtime'; }
    protected function title(): string       { return 'Work Overtime'; }

    protected function generateId(): ?string
    {
        return 'OT' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);
    }

    protected function datatableColumns(): array
    {
        return [
            'overtime_date' => 'Date',
            'division_code' => 'Division',
            'employee_name' => 'Employee',
            'activity_name' => 'Activity',
            'start_time'    => 'Start',
            'end_time'      => 'End',
            'duration_hours'=> 'Duration (h)',
        ];
    }

    protected function rules(Request $request): array
    {
        return [
            'overtime_date'   => 'required|date',
            'division_code'   => 'required|string|max:50',
            'employee_code'   => 'required|string|max:100',
            'activity_code'   => 'required|string|max:100',
            'start_time'      => 'required|string|max:10',
            'end_time'        => 'required|string|max:10',
            'duration_hours'  => 'required|numeric|min:0.1',
            'remark'          => 'nullable|string|max:255',
            'block_code'      => 'nullable|string|max:50',
            'order_number'    => 'nullable|string|max:100',
            'cost_center'     => 'nullable|string|max:100',
        ];
    }

    protected function mapRow(Request $request): array
    {
        $emp      = Employee::where('employee_code', $request->employee_code)->first();
        $activity = Activity::where('activity_code', $request->activity_code)->first();

        // Lookup mandor from t_user_assignment
        $mandor = DB::table('t_user_assignment')
            ->where('worker_employee_code', $request->employee_code)
            ->whereDate('assignment_valid_from', '<=', now())
            ->whereDate('assignment_valid_to', '>=', now())
            ->first();

        return [
            'overtime_date'          => $request->overtime_date,
            'estate_code'            => $this->estateCode(),
            'division_code'          => trim($request->division_code),
            'employee_code'          => trim($request->employee_code),
            'employee_name'          => $emp?->employee_name ?? '',
            'mandor_employee_code'   => $mandor?->mandor_employee_code ?? null,
            'mandor_employee_name'   => $mandor?->mandor_employee_name ?? null,
            'activity_code'          => trim($request->activity_code),
            'activity_name'          => $activity?->activity_name ?? '',
            'block_code'             => $request->block_code ?: null,
            'order_number'           => $request->order_number ?: null,
            'cost_center'            => $request->cost_center ?: null,
            'start_time'             => $request->start_time,
            'end_time'               => $request->end_time,
            'duration_hours'         => (float) $request->duration_hours,
            'remark'                 => $request->remark ?: null,
            'is_approved'            => false,
            'is_closed'              => false,
            'integration_status'     => -1,
        ];
    }

    protected function formData(): array
    {
        $estate = $this->estateCode();
        $today  = Carbon::today()->toDateString();

        return [
            'divisions'  => $this->divisions(),
            'employees'  => Employee::byEstate($estate)
                                ->orderBy('employee_code')
                                ->get(['employee_code', 'employee_name', 'employee_division_code']),
            'activities' => Activity::when($this->companyId(), fn($q)=>$q->where('company_id',$this->companyId()))
                                ->orderBy('activity_code')
                                ->get(['activity_code', 'activity_name', 'activity_uom', 'cost_by_block',
                                       'cost_by_order_number', 'cost_by_cost_center']),
            'blocks'     => Block::where('estate_code', $estate)
                                ->whereDate('valid_from', '<=', $today)
                                ->whereDate('valid_to', '>=', $today)
                                ->orderBy('block_code')
                                ->get(['division_code', 'block_code', 'block_name']),
            'uoms'       => Uom::orderBy('uom_code')->get(['uom_code', 'uom_desc']),
        ];
    }

    // ── AJAX: activity details (cost type flags) ──────────────────────────────
    public function activityDetails(Request $request): JsonResponse
    {
        $activity = Activity::where('activity_code', $request->query('activity_code'))->first();
        if (! $activity) {
            return response()->json(['status' => 'ERROR', 'data' => null]);
        }
        return response()->json(['status' => 'OK', 'data' => $activity]);
    }
}
