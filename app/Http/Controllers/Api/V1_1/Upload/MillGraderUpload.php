<?php

namespace App\Http\Controllers\Api\V1_1\Upload;

use App\Models\Transaction\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Handles the mill_grader upload bucket:
 *   T_OPH_MG_Schema_List        -> t_mill_grader_oph (upsert by id VARCHAR)
 *   T_OPH_Person_MG_Schema_List -> t_oph_mill_grader_persons (dedup)
 *
 * t_mill_grader_oph.id is VARCHAR NULL-default (application-generated).
 * Laravel's t_mill_grader_oph only stores the basic grader columns; mobile
 * sends bunches/deduction too but those columns don't exist yet (deferred).
 * Only present columns are mapped.
 *
 * Also handles muster_chit_report bucket:
 *   T_Harvester_Assignment_Schema_List   -> t_harvester_assignment (insert_batch)
 *   T_General_Worker_Assignment_List     -> t_general_worker_assignment (insert_batch)
 */
final class MillGraderUpload
{
    public function __construct(
        private ?int $companyId,
        private User $user,
    ) {}

    public function handle(array $bucket): void
    {
        $this->millGraderOph($bucket['T_OPH_MG_Schema_List'] ?? []);
        $this->millGraderPersons($bucket['T_OPH_Person_MG_Schema_List'] ?? []);
    }

    public function handleMusterChit(array $bucket): void
    {
        $this->harvesterAssignment($bucket['T_Harvester_Assignment_Schema_List'] ?? []);
        $this->generalWorkerAssignment($bucket['T_General_Worker_Assignment_List'] ?? []);
    }

    // ── t_mill_grader_oph ─────────────────────────────────────────────────────
    private function millGraderOph(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $id = Normalizer::str($r, 'oph_mg_id') ?? Normalizer::str($r, 'oph_id');
            if ($id === null) continue;

            $payload = [
                'id'                    => $id,
                'company_id'            => $this->companyId,
                'estate_code'           => Normalizer::str($r, 'oph_estate_code'),
                'plant_code'            => Normalizer::str($r, 'oph_plant_code'),
                'division_code'         => Normalizer::str($r, 'oph_division_code'),
                'block_code'            => Normalizer::str($r, 'oph_block_code'),
                'tph_code'              => Normalizer::str($r, 'oph_tph_code'),
                'grader_employee_code'  => Normalizer::str($r, 'oph_grader_employee_code')
                                            ?? Normalizer::str($r, 'kerani_panen_employee_code'),
                'grader_employee_name'  => Normalizer::str($r, 'oph_grader_employee_name')
                                            ?? Normalizer::str($r, 'kerani_panen_employee_name'),
                'is_approved'           => Normalizer::intOr0($r, 'is_approved'),
                'is_closed'             => 0,
                'integration_status'    => -1,
                'created_by'            => Normalizer::str($r, 'oph_created_by') ?? $this->actor(),
                'updated_by'            => Normalizer::str($r, 'oph_updated_by') ?? $this->actor(),
                'created_at'            => Normalizer::timestamp($r, 'oph_created_date', 'oph_created_time'),
                'updated_at'            => Carbon::now(),
            ];

            $existing = DB::table('t_mill_grader_oph')->where('id', $id)->first();
            if (! $existing) {
                DB::table('t_mill_grader_oph')->insert($payload);
            } else {
                $upd = $payload;
                unset($upd['id'], $upd['created_at']);
                $upd['updated_at'] = Carbon::now();
                DB::table('t_mill_grader_oph')->where('id', $id)->update($upd);
            }
        }
    }

    /** t_oph_mill_grader_persons: dedup by mill_grader_oph_id + employee_code. */
    private function millGraderPersons(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $ophId   = Normalizer::str($r, 'oph_id');
            $empCode = Normalizer::str($r, 'oph_person_employee_code');
            if ($ophId === null || $empCode === null) continue;

            $exists = DB::table('t_oph_mill_grader_persons')
                ->where('mill_grader_oph_id', $ophId)
                ->where('employee_code', $empCode)
                ->exists();
            if ($exists) continue;

            DB::table('t_oph_mill_grader_persons')->insert([
                'company_id'        => $this->companyId,
                'mill_grader_oph_id'=> $ophId,
                'employee_code'     => $empCode,
                'employee_name'     => Normalizer::str($r, 'oph_person_employee_name'),
            ]);
        }
    }

    // ── t_harvester_assignment ────────────────────────────────────────────────
    private function harvesterAssignment(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $empCode = Normalizer::str($r, 'harvester_employee_code');
            $date    = Normalizer::date($r, 'assignment_date');
            if ($empCode === null || $date === null) continue;

            DB::table('t_harvester_assignment')->insert([
                'company_id'              => $this->companyId,
                'assignment_date'         => $date,
                'estate_code'             => Normalizer::str($r, 'estate_code'),
                'division_code'           => Normalizer::str($r, 'division_code'),
                'mandor_employee_code'    => Normalizer::str($r, 'mandor_employee_code'),
                'mandor_employee_name'    => Normalizer::str($r, 'mandor_employee_name'),
                'harvester_employee_code' => $empCode,
                'harvester_employee_name' => Normalizer::str($r, 'harvester_employee_name'),
                'block_code'              => Normalizer::str($r, 'block_code'),
                'tph_code'                => Normalizer::str($r, 'tph_code'),
                'created_by'              => $this->actor(),
                'updated_by'              => $this->actor(),
                'created_at'              => Carbon::now(),
                'updated_at'              => Carbon::now(),
            ]);
        }
    }

    // ── t_general_worker_assignment ───────────────────────────────────────────
    private function generalWorkerAssignment(array $rows): void
    {
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $empCode = Normalizer::str($r, 'worker_employee_code');
            $date    = Normalizer::date($r, 'assignment_date');
            if ($empCode === null || $date === null) continue;

            DB::table('t_general_worker_assignment')->insert([
                'company_id'          => $this->companyId,
                'assignment_date'     => $date,
                'estate_code'         => Normalizer::str($r, 'estate_code'),
                'division_code'       => Normalizer::str($r, 'division_code'),
                'mandor_employee_code'=> Normalizer::str($r, 'mandor_employee_code'),
                'mandor_employee_name'=> Normalizer::str($r, 'mandor_employee_name'),
                'worker_employee_code'=> $empCode,
                'worker_employee_name'=> Normalizer::str($r, 'worker_employee_name'),
                'activity_code'       => Normalizer::str($r, 'activity_code'),
                'activity_name'       => Normalizer::str($r, 'activity_name'),
                'block_code'          => Normalizer::str($r, 'block_code'),
                'order_number'        => Normalizer::str($r, 'order_number'),
                'created_by'          => $this->actor(),
                'updated_by'          => $this->actor(),
                'created_at'          => Carbon::now(),
                'updated_at'          => Carbon::now(),
            ]);
        }
    }

    private function actor(): string
    {
        return (string) ($this->user->user_employee_code ?: $this->user->user_name ?: $this->user->id);
    }
}
