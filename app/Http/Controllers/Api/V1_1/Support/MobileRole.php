<?php

namespace App\Http\Controllers\Api\V1_1\Support;

/**
 * Single source of truth for mapping the Laravel role_code (m_roles) to the
 * legacy CI3 mobile "user_role" numeric code and the mobile role string used
 * in the login payload (Roles_Schema[].user_roles) and upload buckets.
 *
 * The CI3 API branches its payload by a numeric user_role. Laravel identifies
 * roles by role_code. This map keeps the mobile JSON contract intact while the
 * backend uses the redesigned role model.
 *
 * Confirmed mapping (with the product owner):
 *   5  harvest_clerk           <- checker_palm
 *   6  transport_clerk         <- ramp_dispatch_palm
 *   7  field_staff             <- staff            (FIELD worker, NOT estate_staff/office)
 *   9  harvest_clerk_coconut   <- checker_coconut
 *   10 transport_clerk_coconut <- ramp_dispatch_coconut
 *   11 mill_grader             <- mill_grader
 *   2  (supervisi)             <- estate_manager
 *   3  (supervisi)             <- asst_manager
 *   23 (wh_clerk)              <- warehouse_clerk
 *   33 (store_clerk)           <- store_clerk
 */
final class MobileRole
{
    /** role_code (Laravel) => CI3 numeric user_role. */
    private const CODE_TO_NUM = [
        'estate_manager'        => 2,
        'asst_manager'          => 3,
        'checker_palm'          => 5,
        'ramp_dispatch_palm'    => 6,
        'staff'                 => 7,
        'checker_coconut'       => 9,
        'ramp_dispatch_coconut' => 10,
        'mill_grader'           => 11,
        'warehouse_clerk'       => 23,
        'store_clerk'           => 33,
    ];

    /** CI3 numeric user_role => mobile role string used in Roles_Schema. */
    private const NUM_TO_ROLE_STRING = [
        2  => 'estate_manager',
        3  => 'assistant_manager',
        5  => 'harvest_clerk',
        6  => 'transport_clerk',
        7  => 'field_staff',
        9  => 'harvest_clerk_coconut',
        10 => 'transport_clerk_coconut',
        11 => 'mill_grader',
        23 => 'wh_clerk',
        33 => 'store_clerk',
    ];

    /** Resolve the CI3 numeric user_role from a Laravel role_code (0 if unmapped). */
    public static function toNumber(string $roleCode): int
    {
        return self::CODE_TO_NUM[$roleCode] ?? 0;
    }

    /** Resolve the mobile role string for Roles_Schema from a numeric role. */
    public static function roleString(int $num): ?string
    {
        return self::NUM_TO_ROLE_STRING[$num] ?? null;
    }

    /**
     * Build the Roles_Schema array exactly like CI3 get_roles():
     * - superadmin (username) => [harvest_clerk, transport_clerk, field_staff]
     * - otherwise a single-element list with the mapped role string.
     */
    public static function rolesSchema(int $num, string $username, int $userId): array
    {
        if ($username === 'superadmin') {
            return [
                ['user_id' => $userId, 'user_roles' => 'harvest_clerk'],
                ['user_id' => $userId, 'user_roles' => 'transport_clerk'],
                ['user_id' => $userId, 'user_roles' => 'field_staff'],
            ];
        }
        $role = self::roleString($num);
        if ($role === null) {
            return [];
        }
        return [['user_id' => $userId, 'user_roles' => $role]];
    }
}
