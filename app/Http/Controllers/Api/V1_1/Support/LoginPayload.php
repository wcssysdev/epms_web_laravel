<?php

namespace App\Http\Controllers\Api\V1_1\Support;

use App\Models\Transaction\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Builds the EPMS mobile login payload, replicating the CI3 Auth::login_post
 * JSON contract. Field names in the output follow the CI3 mobile contract; the
 * data is sourced from the redesigned Laravel schema and aliased accordingly.
 *
 * Scope so far:
 *   - reset_master_data flags (MasterDataReset)
 *   - global: M_Config_Schema, Roles_Schema, T_Attendance_Schema + master schemas
 *   - role block: field_staff (role 7 / role_code "staff")
 * Other role blocks (harvest_clerk, transport_clerk, coconut, supervisi) are
 * added in later batches.
 */
final class LoginPayload
{
    public function __construct(
        private User $user,
        private int $roleNum,
        private string $username,
        private bool $isEmpty,
        private ?string $lastLogin,
        private string $token,
        private ?int $loginId = null,
    ) {}

    private function cfg(): ?object
    {
        return $this->user->companyConfig;
    }

    private function companyId(): ?int
    {
        return $this->user->company_id;
    }

    private function estateCode(): string
    {
        return $this->cfg()->estate_code ?? '';
    }

    private function plantCode(): string
    {
        return $this->cfg()->plant_code ?? '';
    }

    private function today(): string
    {
        return Carbon::today()->toDateString();
    }

    /** Assemble the full payload array (order mirrors CI3). */
    public function build(): array
    {
        $isWarehouse = in_array($this->roleNum, [23, 33], true);

        $data = [];
        $data['reset_master_data'] = MasterDataReset::build($this->isEmpty, $this->lastLogin, $isWarehouse, $this->companyId());
        $data['global'] = $this->buildGlobal($data['reset_master_data']);
        $this->buildRoleBlock($data);

        // login_device_id is injected after login_log insert (set by controller).
        if ($this->loginId !== null) {
            $data['global']['M_Config_Schema'][0]['login_device_id'] = $this->loginId;
        }

        return $data;
    }

    // ── GLOBAL ────────────────────────────────────────────────────────────────
    private function buildGlobal(array $reset): array
    {
        $g = [];
        $g['M_Config_Schema'] = $this->configSchema();
        $g['Roles_Schema']    = MobileRole::rolesSchema($this->roleNum, $this->username, (int) $this->user->id);

        // Master schemas: filled when the corresponding reset flag is 1, else [].
        $g['M_Estate_Schema']            = ($reset['estate'] ?? 0) == 1 ? $this->estateSchema() : [];
        $g['M_Division_Schema']          = ($reset['division'] ?? 0) == 1 ? $this->divisionSchema() : [];
        $g['M_Block_Schema']             = ($reset['block'] ?? 0) == 1 ? $this->blockSchema() : [];
        $g['M_Employee_Schema']          = ($reset['employee'] ?? 0) == 1 ? $this->employeeSchema() : [];
        $g['M_Activity_Schema']          = ($reset['activity'] ?? 0) == 1 ? $this->activitySchema() : [];
        $g['M_Attendance_Schema']        = $this->attendanceTypeSchema();      // always
        $g['M_Vendor_Schema']            = $this->vendorSchema();              // always
        $g['M_Customer_Code_Schema']     = $this->customerCodeSchema();        // always
        $g['M_NP_Material_Schema']       = $this->npMaterialSchema();          // always
        $g['M_TPH_Schema']               = $this->tphSchema();                 // always
        $g['MC_OPH_Card_Schema']         = $this->ophCardSchema();             // always
        $g['MC_SPB_Card_Schema']         = $this->fdnCardSchema();             // always
        $g['M_Uom_Schema']               = $this->uomSchema();                 // always
        $g['M_Material_Schema']          = ($reset['material'] ?? 0) == 1 ? $this->materialSchema() : [];
        $g['M_Receiving_Point_Schema']   = ($reset['ramp'] ?? 0) == 1 ? $this->receivingPointSchema() : [];
        $g['M_Destination_Schema']       = ($reset['delivery_destination'] ?? 0) == 1 ? $this->destinationSchema() : [];

        // T_Attendance_Schema: generated attendance rows for this user's gang.
        $g['T_Attendance_Schema'] = $this->tAttendanceSchema();

        return $g;
    }

    /** get_config() + injected login fields. */
    private function configSchema(): array
    {
        $cfg = $this->cfg();
        $row = $cfg ? (array) $cfg->getAttributes() : [];

        // Map/booleanize per CI3 get_config().
        $countryCode = $row['country_code'] ?? null;
        if (empty($countryCode)) {
            $countryCode = 'MY';
        }

        $out = [
            'config_id'        => 1,
            'estate_code'      => $this->estateCode(),
            'estate_name'      => $row['estate_name'] ?? null,
            'plant_code'       => $this->plantCode(),
            'profile_name'     => $row['profile_name'] ?? null,
            'country_code'     => $countryCode,
            'sap_client'       => $row['sap_client'] ?? null,
            'is_fixed_platform'=> (bool) ($row['is_fixed_platform'] ?? false),
            'is_lock_system'   => (bool) ($row['is_lock_system'] ?? false),
            'attendance_default_value'        => $row['attendance_default_value'] ?? null,
            'attendance_normal_default_value' => $row['attendance_normal_default_value'] ?? null,
            'daily_overtime_max_limit'        => (int) ($row['daily_overtime_max_limit'] ?? 0),
            'max_oph_restan'                  => (int) ($row['max_oph_restan'] ?? 0),
            'cutter_distribution_value'       => (int) ($row['cutter_distribution_value'] ?? 0),
            'carrier_distribution_value'      => (int) ($row['carrier_distribution_value'] ?? 0),
            'cutter_lf_distribution_value'    => (int) ($row['cutter_lf_distribution_value'] ?? 0),
            'carrier_lf_distribution_value'   => (int) ($row['carrier_lf_distribution_value'] ?? 0),
        ];

        // Injected login fields (CI3 parity).
        $out['token']     = $this->token;
        $emp = $this->user->user_employee_code;
        if ($emp === null || $emp === '') {
            $out['employee_code'] = $this->user->user_name;
            $out['employee_name'] = null;
        } else {
            $out['employee_code'] = $emp;
            $out['employee_name'] = $this->user->user_name;
        }
        $out['user_id']    = (int) $this->user->id;
        $out['api_root']   = url('/') . '/v1/';
        $out['login_date'] = $this->today();
        $out['login_time'] = Carbon::now()->format('H:i:s');

        // allowed_attendance_codes_for_work_assignment => array of {allowed_attendance_code}
        $codes = [];
        $raw = $row['allowed_attendance_codes'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $c) {
                    $codes[] = ['allowed_attendance_code' => $c];
                }
            }
        }
        $out['allowed_attendance_codes_for_work_assignment'] = $codes;

        return [$out];
    }

    // ── MASTER SCHEMAS (aliased to CI3 contract names) ──────────────────────────
    private function estateSchema(): array
    {
        return DB::table('m_estate')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('estate_code')
            ->get()
            ->map(fn ($r) => [
                'estate_id'         => (int) $r->id,
                'estate_code'       => $r->estate_code,
                'estate_name'       => $r->estate_name,
                'estate_plant_code' => $r->estate_plant_code,
            ])->all();
    }

    private function divisionSchema(): array
    {
        return DB::table('m_division')
            ->where('estate_code', $this->estateCode())
            ->whereDate('valid_from', '<=', $this->today())
            ->whereDate('valid_to', '>=', $this->today())
            ->orderBy('division_code')
            ->get()
            ->map(fn ($r) => [
                'division_id'           => (int) $r->id,
                'division_code'         => $r->division_code,
                'division_name'         => $r->division_name,
                'division_estate_code'  => $r->estate_code,
                'division_valid_from'   => $r->valid_from,
                'division_valid_to'     => $r->valid_to,
            ])->all();
    }

    private function blockSchema(): array
    {
        return DB::table('m_block')
            ->where('estate_code', $this->estateCode())
            ->whereDate('valid_from', '<=', $this->today())
            ->whereDate('valid_to', '>=', $this->today())
            ->orderBy('block_code')
            ->get()
            ->map(fn ($r) => [
                'block_id'            => (int) $r->id,
                'block_code'          => (string) $r->block_code,
                'block_name'          => $r->block_name,
                'block_estate_code'   => $r->estate_code,
                'block_division_code' => $r->division_code,
                'block_crop_type'     => $r->crop_type,
                'block_valid_from'    => $r->valid_from,
                'block_valid_to'      => $r->valid_to,
            ])->all();
    }

    private function employeeSchema(): array
    {
        $profile = $this->cfg()->profile_name ?? null;
        return DB::table('m_employee')
            ->when($profile, fn ($q) => $q->where('employee_profile', $profile))
            ->whereDate('valid_from', '<=', $this->today())
            ->whereDate('valid_to', '>=', $this->today())
            ->orderBy('employee_code')
            ->get()
            ->map(fn ($r) => [
                'employee_id'                  => (int) $r->id,
                'employee_code'                => $r->employee_code,
                'employee_name'                => $r->employee_name,
                'employee_valid_from'          => $r->valid_from,
                'employee_valid_to'            => $r->valid_to,
                'employee_job_code'            => $r->employee_job_code,
                'employee_profile'             => $r->employee_profile,
                'employee_gang_allotment_code' => $r->employee_department,
                'employee_vendor'              => $r->employee_vendor,
            ])->all();
    }

    private function activitySchema(): array
    {
        return DB::table('m_activity')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('activity_code')
            ->get()
            ->map(fn ($r) => [
                'activity_id'        => (int) $r->id,
                'activity_code'      => $r->activity_code,
                'activity_name'      => $r->activity_name,
                'activity_uom'       => $r->activity_uom,
                'activity_uom_name'  => $r->activity_uom_name ?? null,
            ])->all();
    }

    private function attendanceTypeSchema(): array
    {
        return DB::table('m_attendance')
            ->orderBy('attendance_code')
            ->get()
            ->map(fn ($r) => [
                'attendance_id'   => (int) $r->id,
                'attendance_code' => $r->attendance_code,
                'attendance_desc' => $r->attendance_desc,
            ])->all();
    }

    private function vendorSchema(): array
    {
        return DB::table('m_vendor')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('vendor_code')
            ->get()
            ->map(fn ($r) => [
                'vendor_id'   => (int) $r->id,
                'vendor_code' => $r->vendor_code,
                'vendor_name' => $r->vendor_name,
            ])->all();
    }

    private function customerCodeSchema(): array
    {
        return DB::table('m_customer_code')
            ->where('plant_code', $this->plantCode())
            ->orderBy('customer_code')
            ->get()
            ->map(fn ($r) => [
                'customer_code_id' => (int) $r->id,
                'customer_code'    => $r->customer_code,
            ])->all();
    }

    private function npMaterialSchema(): array
    {
        $i = 0;
        return DB::table('m_non_palm_material')
            ->orderBy('material_desc')
            ->get()
            ->map(function ($r) use (&$i) {
                $i++;
                return [
                    'non_palm_material_id'   => $i,
                    'non_palm_material_code' => $r->material_code,
                    'non_palm_material_desc' => $r->material_desc,
                    'non_palm_material_uom'  => $r->material_uom,
                    'non_palm_material_plant_code' => $r->plant_code,
                ];
            })->all();
    }

    private function tphSchema(): array
    {
        return DB::table('m_tph')
            ->where('estate_code', $this->estateCode())
            ->whereDate('valid_from', '<=', $this->today())
            ->whereDate('valid_to', '>=', $this->today())
            ->orderBy('tph_code')
            ->get()
            ->map(fn ($r) => [
                'tph_id'            => (int) $r->id,
                'tph_code'          => (string) $r->tph_code,
                'tph_estate_code'   => $r->estate_code,
                'tph_division_code' => $r->division_code,
                'tph_block_code'    => (string) $r->block_code,
                'tph_section_code'  => $r->section_code,
                'tph_valid_from'    => $r->valid_from,
                'tph_valid_to'      => $r->valid_to,
                'tph_latitude'      => $r->latitude,
                'tph_longitude'     => $r->longitude,
            ])->all();
    }

    private function ophCardSchema(): array
    {
        return DB::table('mc_oph_card')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderByDesc('oph_card_id')
            ->get()
            ->map(fn ($r) => [
                'oph_card_id'   => $r->oph_card_id,
                'division_code' => $r->division_code,
            ])->all();
    }

    private function fdnCardSchema(): array
    {
        return DB::table('mc_fdn_card')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderByDesc('fdn_card_id')
            ->get()
            ->map(fn ($r) => [
                'fdn_card_id'   => $r->fdn_card_id,
                'division_code' => $r->division_code,
            ])->all();
    }

    private function uomSchema(): array
    {
        return DB::table('m_uom')
            ->orderBy('uom_code')
            ->get()
            ->map(fn ($r) => [
                'uom_code' => $r->uom_code,
                'uom_desc' => $r->uom_desc,
            ])->all();
    }

    private function materialSchema(): array
    {
        return DB::table('m_material')
            ->where('plant_code', $this->plantCode())
            ->orderBy('material_name')
            ->get()
            ->map(fn ($r) => [
                'material_id'   => (int) $r->id,
                'material_code' => $r->material_code,
                'material_name' => $r->material_name,
                'material_uom'  => $r->material_uom,
            ])->all();
    }

    private function receivingPointSchema(): array
    {
        return DB::table('m_receiving_point')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('receiving_point_code')
            ->get()
            ->map(fn ($r) => [
                'receiving_point_id'   => (int) $r->id,
                'receiving_point_code' => $r->receiving_point_code,
            ])->all();
    }

    private function destinationSchema(): array
    {
        return DB::table('m_destination')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('destination_code')
            ->get()
            ->map(fn ($r) => [
                'destination_id'   => (int) $r->id,
                'destination_code' => $r->destination_code,
                'destination_name' => $r->destination_name,
            ])->all();
    }

    /**
     * T_Attendance_Schema — generated attendance rows for the user's gang
     * (CI3 get_t_attendance): m_field_staff_gang -> m_gang_employee -> m_employee.
     */
    private function tAttendanceSchema(): array
    {
        $internal = $this->user->user_internal_employee_code;
        if (! $internal) {
            return [];
        }
        $normalCode = $this->cfg()->attendance_normal_default_value ?? null;
        $desc = $normalCode
            ? DB::table('m_attendance')->where('attendance_code', $normalCode)->value('attendance_desc')
            : null;

        $rows = DB::table('m_field_staff_gang as fsg')
            ->join('m_gang_employee as ge', 'ge.gang_code', '=', 'fsg.field_staff_gang_code')
            ->join('m_employee as e', 'e.employee_code', '=', 'ge.gang_employee_code')
            ->where('fsg.field_staff_employee_code', $internal)
            ->whereDate('e.valid_from', '<=', $this->today())
            ->whereDate('e.valid_to', '>=', $this->today())
            ->orderBy('ge.gang_code')->orderBy('ge.gang_employee_code')
            ->get(['ge.gang_employee_code', 'ge.gang_employee_name', 'ge.gang_code']);

        $now = Carbon::now()->format('H:i:s');
        $out = [];
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $out[] = [
                'attendance_id'                  => $i,
                'attendance_mandor_employee_code'=> '',
                'attendance_mandor_employee_name'=> '',
                'attendance_employee_code'       => $r->gang_employee_code,
                'attendance_employee_name'       => $r->gang_employee_name,
                'attendance_gang_allotment_code' => $r->gang_code,
                'attendance_date'                => $this->today(),
                'attendance_code'                => $normalCode,
                'attendance_desc'                => $desc,
                'attendance_created_by'          => $internal,
                'attendance_created_date'        => $this->today(),
                'attendance_created_time'        => $now,
                'attendance_updated_by'          => $internal,
                'attendance_updated_date'        => $this->today(),
                'attendance_updated_time'        => $now,
            ];
        }
        return $out;
    }

    // ── ROLE BLOCKS ─────────────────────────────────────────────────────────────
    private function buildRoleBlock(array &$data): void
    {
        // BATCH 1b: field_staff (role 7). Other roles added in 1c/1d.
        if ($this->roleNum === 7) {
            $data['field_staff'] = $this->fieldStaffBlock();
        }
    }

    private function fieldStaffBlock(): array
    {
        $emp = $this->user->user_employee_code;
        $restan = $this->ophRestanForFieldStaff($emp);

        return [
            'T_Workplan_Schema'         => $this->workplanSchema(),
            'T_Harvesting_Plan_Schema'  => $this->harvestingPlanSchema(null),
            'M_Wbs'                     => $this->wbsSchema(),
            'Laporan_Restan'            => $restan,
            'Laporan_Panen_Kemarin'     => $this->listOphScanningPlatform($restan),
        ];
    }

    private function workplanSchema(): array
    {
        return DB::table('t_workplan')
            ->leftJoin('m_block', function ($j) {
                $j->on('t_workplan.block_code', '=', 'm_block.block_code')
                  ->on('t_workplan.division_code', '=', 'm_block.division_code')
                  ->where('m_block.estate_code', '=', $this->estateCode());
            })
            ->whereDate('t_workplan.workplan_date', $this->today())
            ->where('t_workplan.is_approved', 1)
            ->orderByDesc('t_workplan.id')
            ->get(['t_workplan.*', 'm_block.block_name'])
            ->map(function ($r) {
                $row = (array) $r;
                $row['workplan_id']          = (int) $r->id;
                $row['workplan_date']        = $r->workplan_date;
                $row['workplan_total_hk']    = (int) ($r->total_hk ?? 0);
                $row['workplan_target']      = (int) ($r->total_qty_target ?? 0);
                $row['workplan_is_approved'] = (int) ($r->is_approved ?? 0);
                $row['materials'] = DB::table('t_workplan_material')
                    ->where('workplan_id', $r->id)->orderByDesc('workplan_id')->get()->all();
                return $row;
            })->all();
    }

    private function harvestingPlanSchema(?string $faCode): array
    {
        $q = DB::table('t_harvesting_plan')
            ->join('m_block', function ($j) {
                $j->on('t_harvesting_plan.block_code', '=', 'm_block.block_code')
                  ->on('t_harvesting_plan.division_code', '=', 'm_block.division_code');
            })
            ->whereDate('t_harvesting_plan.plan_date', $this->today())
            ->where('t_harvesting_plan.is_approved', 1)
            ->where('m_block.estate_code', $this->estateCode());

        if ($faCode) {
            $division = DB::table('m_assistant_manager_division')
                ->where('assistant_manager_code', $faCode)->value('division_code');
            $q->where('t_harvesting_plan.division_code', $division);
        }

        return $q->orderByDesc('t_harvesting_plan.id')
            ->get(['t_harvesting_plan.*', 'm_block.block_name'])
            ->map(function ($r) {
                $row = (array) $r;
                $row['harvesting_plan_id']            = (int) $r->id;
                $row['harvesting_plan_date']          = $r->plan_date;
                $row['harvesting_plan_division_code'] = $r->division_code;
                $row['harvesting_plan_block_code']    = $r->block_code;
                $row['harvesting_plan_total_hk']      = (int) ($r->total_hk ?? 0);
                $row['harvesting_plan_is_approved']   = (int) ($r->is_approved ?? 0);
                return $row;
            })->all();
    }

    private function wbsSchema(): array
    {
        return DB::table('m_wbs')
            ->when($this->companyId(), fn ($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('wbs_code')
            ->get()
            ->map(fn ($r) => [
                'wbs_id'         => (int) $r->id,
                'wbs_code'       => $r->wbs_code,
                'wbs_name'       => $r->wbs_name,
                'wbs_group_code' => $r->wbs_group_code,
                'wbs_group_name' => $r->wbs_group_name,
            ])->all();
    }

    /**
     * get_oph_restan for field_staff: OPH not yet in a CP detail, non-permanent
     * restant, not deleted, person_type=1, filtered by the field staff's
     * division (from m_employee). Returns rows aliased to CI3 contract names.
     */
    private function ophRestanForFieldStaff(?string $fieldStaffEmp): array
    {
        $division = $fieldStaffEmp
            ? DB::table('m_employee')->where('employee_code', $fieldStaffEmp)->value('employee_division_code')
            : null;

        $q = DB::table('t_oph')
            ->leftJoin('t_cp_detail', 't_cp_detail.oph_id', '=', 't_oph.id')
            ->join('t_oph_persons', 't_oph_persons.oph_id', '=', 't_oph.id')
            ->whereNull('t_cp_detail.oph_id')
            ->where('t_oph.is_restant_permanent', 0)
            ->where('t_oph.is_deleted', 0)
            ->where('t_oph_persons.person_type', 1);

        if ($division) {
            $q->where('t_oph.division_code', $division);
        }

        return $q->orderBy('t_oph.created_at')
            ->get([
                't_oph.*',
                't_oph_persons.employee_code as cutter_employee_code',
                't_oph_persons.employee_name as cutter_employee_name',
                't_oph_persons.percentage as cutter_percentage',
                DB::raw("to_char(t_oph.created_at, 'DD/MM/YYYY') as oph_created_date"),
            ])
            ->map(fn ($r) => $this->mapOphRow($r))
            ->all();
    }

    /** Map a t_oph row (Laravel columns) to the CI3 mobile OPH contract. */
    private function mapOphRow(object $r): array
    {
        return [
            'oph_id'                     => $r->id,
            'oph_card_id'                => $r->oph_card_id,
            'oph_harvesting_method'      => $r->harvest_method,
            'oph_deduction_indicator'    => (int) ($r->oph_deduction_indicator ?? 0),
            'oph_estate_code'            => $r->estate_code,
            'oph_plant_code'             => $r->plant_code,
            'oph_division_code'          => $r->division_code,
            'oph_block_code'             => $r->block_code,
            'oph_tph_code'               => $r->tph_code,
            'oph_notes'                  => $r->notes,
            'oph_lat'                    => $r->lat,
            'oph_long'                   => $r->long,
            'mandor_employee_code'       => $r->mandor_employee_code,
            'mandor_employee_name'       => $r->mandor_employee_name,
            'kerani_panen_employee_code' => $r->kerani_panen_employee_code,
            'kerani_panen_employee_name' => $r->kerani_panen_employee_name,
            'cutter_employee_code'       => $r->cutter_employee_code ?? null,
            'cutter_employee_name'       => $r->cutter_employee_name ?? null,
            'cutter_percentage'          => (int) ($r->cutter_percentage ?? 0),
            'bunches_wet'                => (int) $r->bunches_wet,
            'bunches_ripe'               => (int) $r->bunches_ripe,
            'bunches_overripe'           => (int) $r->bunches_overripe,
            'bunches_underripe'          => (int) $r->bunches_underripe,
            'bunches_unripe'             => (int) $r->bunches_unripe,
            'bunches_rotten'             => (int) $r->bunches_rotten,
            'bunches_long_stalk'         => (int) $r->bunches_long_stalk,
            'bunches_empty'              => (int) $r->bunches_empty,
            'bunches_dirty'              => (int) $r->bunches_dirty,
            'bunches_unfresh'            => (int) $r->bunches_unfresh,
            'bunches_old'                => (int) $r->bunches_old,
            'bunches_pest_damaged_old'   => (int) ($r->bunches_pest_damaged_old ?? 0),
            'bunches_pest_damaged_new'   => (int) ($r->bunches_pest_damaged_new ?? 0),
            'bunches_diseased'           => (int) $r->bunches_diseased,
            'bunches_total'              => (int) $r->bunches_total,
            'bunches_not_sent'           => (int) ($r->bunches_not_sent ?? 0),
            'loose_fruits'               => (int) $r->loose_fruits,
            'is_planned'                 => (int) $r->is_planned,
            'is_approved'                => (int) $r->is_approved,
            'is_restant_permanent'       => (int) $r->is_restant_permanent,
            'oph_created_date'           => $r->oph_created_date ?? null,
            'is_backlog'                 => $r->is_backlog ?? null,
        ];
    }

    /** get_list_oph_scanning_platform: mark restan as backlog then merge last-day platform OPH. */
    private function listOphScanningPlatform(array $restan): array
    {
        foreach ($restan as &$row) {
            $row['is_backlog'] = '1';
        }
        unset($row);
        // Last-day platform OPH merge is added with harvest_clerk (1c). For now
        // field_staff returns the restan list (backlog-flagged), matching shape.
        return $restan;
    }
}
