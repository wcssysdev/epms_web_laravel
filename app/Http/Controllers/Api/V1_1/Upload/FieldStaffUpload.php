<?php

namespace App\Http\Controllers\Api\V1_1\Upload;

use App\Models\Transaction\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Handles the field_staff upload bucket (CI3 In.php field_staff branch):
 *   - T_Attendance_Schema_List_Panen -> t_attendance (upsert by employee+date)
 *   - T_Workdone_Schema_List         -> t_workdone (insert; planned-approval lookup)
 *   - T_Workdone_Material_Schema_List-> t_workdone_material (insert)
 *
 * Mobile field names (attendance_*, workdone_*) are remapped to the Laravel
 * columns. Values are normalized per CI3 (null/"", numeric 0, dates Y-m-d).
 */
final class FieldStaffUpload
{
    public function __construct(
        private ?int $companyId,
        private User $user,
    ) {}

    public function handle(array $bucket): void
    {
        $this->attendance($bucket['T_Attendance_Schema_List_Panen'] ?? []);
        $this->workdone($bucket['T_Workdone_Schema_List'] ?? []);
        $this->workdoneMaterial($bucket['T_Workdone_Material_Schema_List'] ?? []);
    }

    /** t_attendance: upsert keyed by employee_code + attendance_date (CI3 parity). */
    private function attendance(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;

            $empCode = Normalizer::str($r, 'attendance_employee_code');
            $date    = Normalizer::date($r, 'attendance_date');
            if ($empCode === null || $date === null) continue;

            $payload = [
                'company_id'            => $this->companyId,
                'attendance_date'       => $date,
                'mandor_employee_code'  => Normalizer::str($r, 'attendance_mandor_employee_code'),
                'mandor_employee_name'  => Normalizer::str($r, 'attendance_mandor_employee_name'),
                'employee_code'         => $empCode,
                'employee_name'         => Normalizer::str($r, 'attendance_employee_name'),
                'attendance_code'       => Normalizer::str($r, 'attendance_code'),
                'attendance_desc'       => Normalizer::str($r, 'attendance_desc'),
                'gang_allotment_code'   => Normalizer::str($r, 'attendance_gang_allotment_code'),
                'work_status'           => 0,   // CI3 hardcodes work_status=0 on upload
                'is_closed'             => 0,   // CI3 hardcodes is_closed=0
                'created_by'            => Normalizer::str($r, 'attendance_created_by'),
                'updated_by'            => Normalizer::str($r, 'attendance_updated_by'),
            ];

            $existing = DB::table('t_attendance')
                ->where('employee_code', $empCode)
                ->whereDate('attendance_date', $date)
                ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
                ->first();

            if ($existing) {
                DB::table('t_attendance')->where('id', $existing->id)->update(array_merge($payload, [
                    'updated_at' => Carbon::now(),
                ]));
            } else {
                DB::table('t_attendance')->insert(array_merge($payload, [
                    'created_at' => Normalizer::timestamp($r, 'attendance_created_date', 'attendance_created_time'),
                    'updated_at' => Carbon::now(),
                ]));
            }
        }
    }

    /** t_workdone: insert; if planned (matching approved t_workplan) copy approval.
     * t_workdone.id is an application-generated VARCHAR PK (not auto), so we use
     * the mobile-supplied workdone_id or generate one. The generated id is also
     * propagated to the material rows keyed by the same mobile workdone_id. */
    private array $workdoneIdMap = [];

    private function workdone(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;

            $mobileId = Normalizer::str($r, 'workdone_id');
            $id = $mobileId ?: ('WD' . Carbon::now()->format('YmdHis') . random_int(1000, 9999));
            if ($mobileId) {
                $this->workdoneIdMap[$mobileId] = $id;
            }

            $date       = Normalizer::date($r, 'workdone_date');
            $activity   = Normalizer::str($r, 'workdone_activity_code');
            $block      = Normalizer::str($r, 'workdone_block_code');
            $order      = Normalizer::str($r, 'workdone_order_number');
            $auc        = Normalizer::str($r, 'workdone_auc_number');
            $costCenter = Normalizer::str($r, 'workdone_cost_center');

            // Planned-approval lookup against an approved workplan.
            $planned = DB::table('t_workplan')
                ->whereDate('workplan_date', $date)
                ->where('activity_code', $activity)
                ->when($block, fn ($q) => $q->where('block_code', $block))
                ->where('is_approved', 1)
                ->first();

            $payload = [
                'id'                   => $id,
                'company_id'           => $this->companyId,
                'workdone_date'        => $date,
                'estate_code'          => Normalizer::str($r, 'workdone_estate_code'),
                'plant_code'           => Normalizer::str($r, 'workdone_plant_code'),
                'division_code'        => Normalizer::str($r, 'workdone_division_code'),
                'activity_code'        => $activity,
                'activity_name'        => Normalizer::str($r, 'workdone_activity_name'),
                'activity_uom'         => Normalizer::str($r, 'workdone_activity_uom'),
                'block_code'           => $block,
                'order_number'         => $order,
                'auc_number'           => $auc,
                'cost_center'          => $costCenter,
                'wbs_code'             => Normalizer::str($r, 'workdone_wbs_code'),
                'wbs_name'             => Normalizer::str($r, 'workdone_wbs_name'),
                'mandor_employee_code' => Normalizer::str($r, 'workdone_mandor_employee_code') ?? null,
                'mandor_employee_name' => Normalizer::str($r, 'workdone_mandor_employee_name') ?? null,
                'employee_code'        => Normalizer::str($r, 'workdone_employee_code'),
                'employee_name'        => Normalizer::str($r, 'workdone_employee_name'),
                'qty'                  => Normalizer::floatOr0($r, 'workdone_qty'),
                'target_qty'           => Normalizer::floatOr0($r, 'workdone_target_qty'),
                'flexrate'             => Normalizer::str($r, 'workdone_flexrate'),
                'start_time'           => Normalizer::str($r, 'workdone_start_time'),
                'end_time'             => Normalizer::str($r, 'workdone_end_time'),
                'duration'             => Normalizer::str($r, 'workdone_duration'),
                'description'          => Normalizer::str($r, 'workdone_remark'),
                'block_status'         => Normalizer::intOr0($r, 'workdone_block_status'),
                'is_planned'           => $planned ? 1 : 0,
                'is_approved'          => $planned ? 1 : 0,
                'approved_by'          => $planned->approved_by ?? null,
                'approved_by_name'     => $planned->approved_by_name ?? null,
                'approved_at'          => $planned->approved_at ?? null,
                'is_closed'            => 0,
                'created_by'           => Normalizer::str($r, 'workdone_created_by'),
                'updated_by'           => Normalizer::str($r, 'workdone_updated_by'),
                'created_at'           => Normalizer::timestamp($r, 'workdone_created_date', 'workdone_created_time'),
                'updated_at'           => Carbon::now(),
            ];

            DB::table('t_workdone')->insert($payload);
        }
    }

    /** t_workdone_material: insert (workdone_material_id dropped, auto PK). */
    private function workdoneMaterial(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $matCode = Normalizer::str($r, 'workdone_material_code');
            if ($matCode === null) continue;

            $mobileWdId = Normalizer::str($r, 'workdone_id');
            $wdId = ($mobileWdId !== null && isset($this->workdoneIdMap[$mobileWdId]))
                ? $this->workdoneIdMap[$mobileWdId]
                : $mobileWdId;

            DB::table('t_workdone_material')->insert([
                'company_id'    => $this->companyId,
                'workdone_id'   => $wdId,
                'material_code' => $matCode,
                'material_name' => Normalizer::str($r, 'workdone_material_name'),
                'material_uom'  => Normalizer::str($r, 'workdone_material_uom'),
                'qty'           => Normalizer::floatOr0($r, 'workdone_material_qty'),
            ]);
        }
    }
}
