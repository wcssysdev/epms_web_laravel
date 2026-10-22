<?php

namespace App\Services\Fusion;

/**
 * LOCKED role map for estate-db-fusion (design LLD-1, Requirements R4 / R6).
 *
 * This is the authoritative translation of a CI3 `user_role` (int) into the
 * target Laravel `role_code` (string) plus the target role `level`. It is a
 * PURE lookup helper: it performs NO database access and NEVER hard-codes the
 * target `role_id` (the SQL Server identity). The int -> role_code map is read
 * from config/fusion.php ('role_map'); the role_code -> role_id resolution is
 * deferred to runtime (seeder / RbacMigrator) against m_roles, after seeding.
 *
 * Mapping rules:
 * - Match by the explicit map only, NOT by numeric id equality (R6.3).
 * - An unmapped source `user_role` resolves to null; the caller then records
 *   the user to the error ledger and SKIPS it — a role is NEVER guessed
 *   (R6.2). Likewise an unknown role_code has a null level.
 *
 * role_code -> level table (LLD-1 "Target level" column; the three NEW roles
 * come from config('fusion.new_roles')):
 *   super_admin            10   (seed-only, not mapped from source)
 *   country_admin          20   (seed-only)
 *   company_admin          30   (seed-only)
 *   admin (Estate Admin)   35
 *   estate_manager         40
 *   asst_manager           50
 *   estate_staff           60
 *   staff                  60
 *   it_staff               60
 *   pc                     60
 *   cs                     60
 *   safety_officer         60   (NEW)
 *   checker_palm           70
 *   ramp_dispatch_palm     70
 *   checker_coconut        70
 *   ramp_dispatch_coconut  70
 *   mill_grader            70
 *   store_it               70
 *   warehouse_clerk        70
 *   store_clerk            70
 *   sustainability_clerk   70   (NEW)
 *   ramp_tractor           70   (NEW)
 *
 * Usage:
 *   $code  = RoleMap::resolveRoleCode(23);        // 'warehouse_clerk'
 *   $level = RoleMap::level($code);               // 70
 *   $estate = RoleMap::isEstateLevel($code);      // true
 *   RoleMap::resolveRoleCode(999);                // null -> caller skips
 */
class RoleMap
{
    /**
     * Highest level reserved for company-wide-and-above tiers.
     *
     * Roles whose level is STRICTLY ABOVE this threshold (i.e. level >= 35) are
     * estate-level/operational and therefore receive an estate_code on their
     * tc_user_access row plus an additive tc_user_scope(estate) row. Roles at
     * or below it — super_admin (10), country_admin (20), company_admin (30) —
     * are not estate-scoped (LLD-1 "Scope shape", design Scope matrix).
     */
    public const COMPANY_LEVEL_CEILING = 30;

    /**
     * Target role level per role_code (design LLD-1).
     *
     * The three NEW roles (safety_officer, sustainability_clerk, ramp_tractor)
     * are intentionally sourced from config('fusion.new_roles') so their level
     * has a single source of truth shared with the seeder; the rest are the
     * LLD-1 static levels, including the seed-only tiers not present in the
     * int -> role_code map.
     */
    private const LEVELS = [
        // Seed-provisioned tiers (LLD-6) — never mapped from a source user_role.
        'super_admin'           => 10,
        'country_admin'         => 20,
        'company_admin'         => 30,

        // Estate-level / operational roles mapped from CI3 user_role (LLD-1).
        'admin'                 => 35, // CI3 "Estate Admin"
        'estate_manager'        => 40,
        'asst_manager'          => 50,
        'estate_staff'          => 60,
        'staff'                 => 60,
        'it_staff'              => 60,
        'pc'                    => 60,
        'cs'                    => 60,
        'checker_palm'          => 70,
        'ramp_dispatch_palm'    => 70,
        'checker_coconut'       => 70,
        'ramp_dispatch_coconut' => 70,
        'mill_grader'           => 70,
        'store_it'              => 70,
        'warehouse_clerk'       => 70,
        'store_clerk'           => 70,
    ];

    /**
     * Resolve a CI3 `user_role` (int) to the target `role_code` (string).
     *
     * Reads the authoritative int -> role_code map from config('fusion.role_map').
     * Returns null when the value is not present in the LOCKED map; the caller
     * records the user to the error ledger and skips it, and NEVER guesses a
     * role (R6.2 / R6.3).
     */
    public static function resolveRoleCode(int $userRole): ?string
    {
        $map = (array) config('fusion.role_map', []);

        return $map[$userRole] ?? null;
    }

    /**
     * Target role level for a `role_code` (design LLD-1), or null if unknown.
     *
     * New-role levels come from config('fusion.new_roles'); all other known
     * roles come from the static LLD-1 table. An unrecognized role_code
     * returns null rather than a guessed level.
     */
    public static function level(string $roleCode): ?int
    {
        $newRoles = (array) config('fusion.new_roles', []);

        if (array_key_exists($roleCode, $newRoles)) {
            return (int) $newRoles[$roleCode];
        }

        if (array_key_exists($roleCode, self::LEVELS)) {
            return self::LEVELS[$roleCode];
        }

        return null;
    }

    /**
     * Whether a `role_code` is estate-level/operational.
     *
     * True for roles at level >= 35 (Estate Admin, estate_manager, asst_manager,
     * staff and operational roles) — those get an estate_code on tc_user_access
     * plus a tc_user_scope(estate) row. False for super_admin / country_admin /
     * company_admin (levels 10/20/30), which are company-wide-and-above.
     *
     * An unknown role_code (null level) is NOT treated as estate-level; the
     * caller should have already skipped an unmapped user.
     */
    public static function isEstateLevel(string $roleCode): bool
    {
        $level = self::level($roleCode);

        return $level !== null && $level > self::COMPANY_LEVEL_CEILING;
    }
}
