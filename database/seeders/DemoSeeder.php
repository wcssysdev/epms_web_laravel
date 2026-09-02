<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed demo data:
     * - 2 countries (MY, ID)
     * - 4 companies (2 per country)
     * - company configs for each company
     * - 1 superadmin (global)
     * - 1 country admin per country
     * - All roles covered across companies
     * - All passwords: 'password'
     */
    public function run(): void
    {
        $password = Hash::make('password');
        $now      = now();

        $this->command->info('Seeding countries...');
        $this->seedCountries();

        $this->command->info('Seeding companies...');
        $companies = $this->seedCompanies();

        $this->command->info('Seeding company configs...');
        $this->seedCompanyConfigs($companies);

        $this->command->info('Seeding roles...');
        $this->seedRoles();

        $this->command->info('Seeding users...');
        $this->seedUsers($companies, $password, $now);

        $this->command->info('Done! All users password: password');
    }

    // ── Countries ─────────────────────────────────────────────────────────────
    private function seedCountries(): void
    {
        $countries = [
            ['code' => 'MY', 'name' => 'Malaysia',  'prefix' => '1'],
            ['code' => 'ID', 'name' => 'Indonesia', 'prefix' => '2'],
        ];

        foreach ($countries as $c) {
            DB::table('m_country')->updateOrInsert(
                ['code' => $c['code']],
                array_merge($c, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    // ── Companies ─────────────────────────────────────────────────────────────
    private function seedCompanies(): array
    {
        $myId = DB::table('m_country')->where('code', 'MY')->value('id');
        $idId = DB::table('m_country')->where('code', 'ID')->value('id');

        $companies = [
            // Malaysia
            ['country_id' => $myId, 'company_code' => '1TEST', 'company_name' => 'TEST COMPANY MY',      'is_active' => true],
            ['country_id' => $myId, 'company_code' => '1TSK',  'company_name' => 'TASIK RAJA ESTATE MY', 'is_active' => true],
            // Indonesia
            ['country_id' => $idId, 'company_code' => '2TEST', 'company_name' => 'TEST COMPANY ID',      'is_active' => true],
            ['country_id' => $idId, 'company_code' => '2SGK',  'company_name' => 'SUNGAI KELAPA ESTATE', 'is_active' => true],
        ];

        $result = [];
        foreach ($companies as $c) {
            DB::table('m_company')->updateOrInsert(
                ['company_code' => $c['company_code']],
                array_merge($c, ['created_at' => now(), 'updated_at' => now()])
            );
            $result[$c['company_code']] = DB::table('m_company')
                ->where('company_code', $c['company_code'])
                ->first();
        }
        return $result;
    }

    // ── Company Configs ───────────────────────────────────────────────────────
    private function seedCompanyConfigs(array $companies): void
    {
        $configs = [
            '1TEST' => [
                'estate_code' => 'TST', 'estate_name' => 'TEST ESTATE', 'plant_code' => 'T001',
                'profile_code' => 'P001', 'profile_name' => 'Test Profile',
                'sap_client' => '100', 'integration_type' => 1,
                'system_is_palm' => true, 'system_is_coconut' => false,
                'system_is_rubber' => false, 'system_is_durian' => false,
                'cutter_distribution_value' => 60, 'carrier_distribution_value' => 40,
                'daily_overtime_max_limit' => 3, 'max_oph_restan' => 0,
            ],
            '1TSK' => [
                'estate_code' => 'TSK', 'estate_name' => 'TASIK RAJA ESTATE', 'plant_code' => 'T002',
                'profile_code' => 'P002', 'profile_name' => 'Tasik Raja Profile',
                'sap_client' => '100', 'integration_type' => 1,
                'system_is_palm' => true, 'system_is_coconut' => true,
                'system_is_rubber' => false, 'system_is_durian' => false,
                'cutter_distribution_value' => 55, 'carrier_distribution_value' => 45,
                'daily_overtime_max_limit' => 3, 'max_oph_restan' => 0,
            ],
            '2TEST' => [
                'estate_code' => 'TID', 'estate_name' => 'TEST ESTATE ID', 'plant_code' => 'I001',
                'profile_code' => 'P003', 'profile_name' => 'Test Profile ID',
                'sap_client' => '200', 'integration_type' => 1,
                'system_is_palm' => true, 'system_is_coconut' => false,
                'system_is_rubber' => true, 'system_is_durian' => false,
                'cutter_distribution_value' => 50, 'carrier_distribution_value' => 50,
                'daily_overtime_max_limit' => 3, 'max_oph_restan' => 0,
            ],
            '2SGK' => [
                'estate_code' => 'SGK', 'estate_name' => 'SUNGAI KELAPA ESTATE', 'plant_code' => 'I002',
                'profile_code' => 'P004', 'profile_name' => 'Sungai Kelapa Profile',
                'sap_client' => '200', 'integration_type' => 2,
                'system_is_palm' => false, 'system_is_coconut' => false,
                'system_is_rubber' => false, 'system_is_durian' => true,
                'cutter_distribution_value' => 50, 'carrier_distribution_value' => 50,
                'daily_overtime_max_limit' => 4, 'max_oph_restan' => 0,
            ],
        ];

        foreach ($configs as $code => $cfg) {
            if (!isset($companies[$code])) continue;
            $companyId = $companies[$code]->id;

            DB::table('m_company_config')->updateOrInsert(
                ['company_id' => $companyId],
                array_merge($cfg, [
                    'company_id'   => $companyId,
                    'is_lock_system' => false,
                    'have_internet_connection' => true,
                    'fdn_oph' => false,
                    'is_fixed_platform' => false,
                    'additional_settings' => json_encode([
                        'take_picture_oph' => 'N', 'take_picture_cp1' => 'N',
                        'take_picture_cp2' => 'N', 'take_picture_fdn' => 'N',
                        'take_location_oph' => 'N', 'take_location_cp1' => 'N',
                        'take_location_cp2' => 'N', 'take_location_fdn' => 'N',
                        'oph_scan_task' => 'N', 'oph_scan_card' => 'N',
                    ]),
                    'allowed_attendance_codes' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    // ── Roles ─────────────────────────────────────────────────────────────────
    private function seedRoles(): void
    {
        $roles = [
            ['role_code' => 'super_admin',          'role_name' => 'Super Admin',             'level' => 10,  'required_system_type' => null],
            ['role_code' => 'country_admin',         'role_name' => 'Country Admin',           'level' => 20,  'required_system_type' => null],
            ['role_code' => 'company_admin',         'role_name' => 'Company Admin',           'level' => 30,  'required_system_type' => null],
            ['role_code' => 'estate_manager',        'role_name' => 'Estate Manager',          'level' => 40,  'required_system_type' => null],
            ['role_code' => 'asst_manager',          'role_name' => 'Assistant Manager',       'level' => 50,  'required_system_type' => null],
            ['role_code' => 'estate_staff',          'role_name' => 'Estate Staff',            'level' => 60,  'required_system_type' => null],
            ['role_code' => 'staff',                 'role_name' => 'Staff',                   'level' => 60,  'required_system_type' => null],
            ['role_code' => 'it_staff',              'role_name' => 'IT Staff',                'level' => 60,  'required_system_type' => null],
            ['role_code' => 'mill_grader',           'role_name' => 'Mill Grader',             'level' => 70,  'required_system_type' => null],
            ['role_code' => 'warehouse_clerk',       'role_name' => 'Warehouse Clerk',         'level' => 70,  'required_system_type' => null],
            ['role_code' => 'store_clerk',           'role_name' => 'Store Clerk',             'level' => 70,  'required_system_type' => null],
            ['role_code' => 'store_it',              'role_name' => 'Store IT',                'level' => 70,  'required_system_type' => null],
            ['role_code' => 'checker_palm',          'role_name' => 'Checker (Palm)',          'level' => 70,  'required_system_type' => 'palm'],
            ['role_code' => 'ramp_dispatch_palm',    'role_name' => 'Ramp - Dispatch (Palm)',  'level' => 70,  'required_system_type' => 'palm'],
            ['role_code' => 'checker_coconut',       'role_name' => 'Checker (Coconut)',       'level' => 70,  'required_system_type' => 'coconut'],
            ['role_code' => 'ramp_dispatch_coconut', 'role_name' => 'Ramp - Dispatch (Coconut)', 'level' => 70, 'required_system_type' => 'coconut'],
        ];

        foreach ($roles as $r) {
            DB::table('m_roles')->updateOrInsert(
                ['role_code' => $r['role_code']],
                array_merge($r, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    // ── Users ─────────────────────────────────────────────────────────────────
    private function seedUsers(array $companies, string $password, $now): void
    {
        // ── 1. Super Admin (global - no company) ──────────────────────────────
        $this->createUser([
            'username'  => 'superadmin',
            'user_name' => 'Super Admin',
            'email'     => 'superadmin@epms.local',
            'password'  => $password,
        ], 'super_admin', null, null, $now);

        // ── 2. Country Admins ──────────────────────────────────────────────────
        $myCountryId = DB::table('m_country')->where('code', 'MY')->value('id');
        $idCountryId = DB::table('m_country')->where('code', 'ID')->value('id');

        $this->createUser([
            'username'  => 'country_admin_my',
            'user_name' => 'Country Admin Malaysia',
            'email'     => 'country.my@epms.local',
            'password'  => $password,
        ], 'country_admin', $myCountryId, null, $now);

        $this->createUser([
            'username'  => 'country_admin_id',
            'user_name' => 'Country Admin Indonesia',
            'email'     => 'country.id@epms.local',
            'password'  => $password,
        ], 'country_admin', $idCountryId, null, $now);

        // ── 3. Company-level users (all roles) ─────────────────────────────────
        // Map: company_code → [role_code, username_prefix, display_name]
        $userMatrix = [
            '1TEST' => [
                ['company_admin',      'admin',          'Company Admin'],
                ['estate_manager',     'mgr',            'Estate Manager'],
                ['asst_manager',       'asst',           'Assistant Manager'],
                ['estate_staff',       'estate_staff',   'Estate Staff'],
                ['staff',              'staff',          'Staff User'],
                ['it_staff',           'it',             'IT Staff'],
                ['mill_grader',        'mill',           'Mill Grader'],
                ['warehouse_clerk',    'wh',             'Warehouse Clerk'],
                ['store_clerk',        'store',          'Store Clerk'],
                ['store_it',           'store_it',       'Store IT'],
                ['checker_palm',       'checker_palm',   'Checker Palm'],
                ['ramp_dispatch_palm', 'ramp_palm',      'Ramp Dispatch Palm'],
            ],
            '1TSK' => [
                ['company_admin',         'admin',          'Admin TSK'],
                ['estate_manager',        'mgr',            'Manager TSK'],
                ['checker_palm',          'checker_palm',   'Checker Palm TSK'],
                ['checker_coconut',       'checker_coconut','Checker Coconut TSK'],
                ['ramp_dispatch_coconut', 'ramp_coconut',   'Ramp Coconut TSK'],
            ],
            '2TEST' => [
                ['company_admin',  'admin',  'Admin TEST ID'],
                ['estate_manager', 'mgr',    'Manager TEST ID'],
                ['estate_staff',   'staff',  'Staff TEST ID'],
            ],
            '2SGK' => [
                ['company_admin',  'admin', 'Admin SGK'],
                ['estate_manager', 'mgr',   'Manager SGK'],
            ],
        ];

        foreach ($userMatrix as $companyCode => $users) {
            if (!isset($companies[$companyCode])) continue;

            $company   = $companies[$companyCode];
            $suffix    = strtolower(str_replace(['1', '2', '3'], ['my', 'id', 'ph'], substr($companyCode, 0, 1)))
                       . '_' . strtolower(substr($companyCode, 1));

            foreach ($users as [$roleCode, $prefix, $displayName]) {
                $this->createUser([
                    'username'  => "{$prefix}_{$suffix}",
                    'user_name' => "{$displayName} ({$companyCode})",
                    'email'     => "{$prefix}.{$suffix}@epms.local",
                    'password'  => $password,
                ], $roleCode, null, $company->id, $now);
            }
        }
    }

    // ── Helper: create user + access ──────────────────────────────────────────
    private function createUser(array $userData, string $roleCode, ?int $countryId, ?int $companyId, $now): void
    {
        $roleId = DB::table('m_roles')->where('role_code', $roleCode)->value('id');
        if (!$roleId) {
            $this->command->warn("Role not found: {$roleCode}, skipping {$userData['username']}");
            return;
        }

        // Upsert user
        $existing = DB::table('tc_user')->where('username', $userData['username'])->first();

        if ($existing) {
            DB::table('tc_user')->where('username', $userData['username'])
                ->update([
                    'user_name'   => $userData['user_name'],
                    'email'       => $userData['email'],
                    'password'    => $userData['password'],
                    'is_active'   => true,
                    'updated_at'  => $now,
                ]);
            $userId = $existing->id;
        } else {
            $userId = DB::table('tc_user')->insertGetId([
                'username'    => $userData['username'],
                'user_name'   => $userData['user_name'],
                'email'       => $userData['email'],
                'password'    => $userData['password'],
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // Upsert access
        $existingAccess = DB::table('tc_user_access')->where('user_id', $userId)->first();
        $accessData = [
            'user_id'    => $userId,
            'role_id'    => $roleId,
            'country_id' => $countryId,
            'company_id' => $companyId,
            'is_active'  => true,
            'updated_at' => $now,
        ];

        if ($existingAccess) {
            DB::table('tc_user_access')->where('user_id', $userId)->update($accessData);
        } else {
            DB::table('tc_user_access')->insert(array_merge($accessData, ['created_at' => $now]));
        }

        $scope = $companyId ? "company:{$companyId}" : ($countryId ? "country:{$countryId}" : 'GLOBAL');
        $this->command->line("  ✓ {$userData['username']} [{$roleCode}] {$scope}");
    }
}
