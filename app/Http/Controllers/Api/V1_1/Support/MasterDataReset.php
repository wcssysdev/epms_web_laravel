<?php

namespace App\Http\Controllers\Api\V1_1\Support;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Builds the login "reset_master_data" flag block.
 *
 * CI3 compared each master's master_data_log timestamp against the client's
 * last_login timestamp: is_master_data_replaced() = 1 when the master was
 * updated after the client's last sync (or when is_empty=1 → force all 1).
 *
 * CI3 addressed master_data_log by positional index ([0]..[20]); Laravel's
 * master_data_log is keyed by table_name, so we map each CI3 output flag to a
 * Laravel table_name and look it up by name (more robust than position).
 *
 * Output keys are the exact CI3 contract keys (estate, division, ...).
 */
final class MasterDataReset
{
    /** CI3 flag key => Laravel master_data_log.table_name (path B: role != 23/33). */
    private const FLAG_TABLE_B = [
        'estate'              => 'm_estate',
        'division'            => 'm_division',
        'block'               => 'm_block',
        'employee'            => 'm_employee',
        'activity'            => 'm_activity',
        'attendance_type'     => 'm_attendance',
        'license_number'      => 'm_vra',
        'ramp'                => 'm_receiving_point',
        'delivery_destination'=> 'm_destination',
        'abw'                 => 'm_abw',
        'material'            => 'm_material',
        'grading'             => 'm_non_palm_material',
        'grouping_gang'       => 'm_gang_employee',
        'vendor'              => 'm_vendor',
        'crop_type'           => 'crop_type',
        'block_crop_type'     => 'm_block',
        'harvesting_method'   => 'm_harvest_method',
        'work_type'           => 'm_worktype',
        'work_center'         => 'm_work_center',
        'measurement_point'   => 'm_meas_point',
        'confirmation_text'   => 'm_confirmation_text',
        'vra_type'            => 'm_vra',
    ];

    /** CI3 flag key => Laravel table_name (path A: warehouse/store clerk, role 23/33). */
    private const FLAG_TABLE_A = [
        'estate'              => 'm_estate',
        'division'            => 'm_division',
        'block'               => 'm_block',
        'employee'            => 'm_employee',
        'activity'            => 'm_activity',
        'attendance_type'     => 'm_attendance',
        'license_number'      => 'm_vra',
        'ramp'                => 'm_receiving_point',
        'delivery_destination'=> 'm_destination',
        'abw'                 => 'm_abw',
        'material'            => 'm_material',
        'grading'             => 'm_non_palm_material',
        'grouping_gang'       => 'm_gang_employee',
        'vendor'              => 'm_vendor',
        'sloc'                => 'm_sloc',
    ];

    /**
     * @param bool        $isEmpty  true => force all flags to 1 (full sync)
     * @param string|null $lastLogin "Y-m-d H:i:s" client last-login, or null
     * @param bool        $isWarehouse role 23/33 uses the smaller path-A key set
     */
    public static function build(bool $isEmpty, ?string $lastLogin, bool $isWarehouse, ?int $companyId): array
    {
        $map = $isWarehouse ? self::FLAG_TABLE_A : self::FLAG_TABLE_B;

        if ($isEmpty) {
            return array_map(fn () => 1, $map);
        }

        // Preload the log timestamps keyed by table_name for this company.
        $logs = DB::table('master_data_log')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->pluck('last_updated_at', 'table_name');

        $flags = [];
        foreach ($map as $flag => $table) {
            $flags[$flag] = self::isReplaced($logs[$table] ?? null, $lastLogin);
        }
        return $flags;
    }

    /** 1 when the master was updated at/after the client's last login, else 0. */
    private static function isReplaced($lastUpdatedAt, ?string $lastLogin): int
    {
        if ($lastUpdatedAt === null) {
            return 1; // never-synced / missing master => force refresh
        }
        if ($lastLogin === null || $lastLogin === ' ' || trim($lastLogin) === '') {
            return 1;
        }
        try {
            return Carbon::parse($lastUpdatedAt)->gt(Carbon::parse($lastLogin)) ? 1 : 0;
        } catch (\Throwable $e) {
            return 1;
        }
    }
}
