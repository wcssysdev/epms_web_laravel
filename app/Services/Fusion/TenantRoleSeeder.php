<?php

namespace App\Services\Fusion;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * TenantRoleSeeder — the tenant/role seeding stage of estate-db-fusion
 * (spec task 4.1).
 *
 * Spec authority:
 *   - design.md sequence diagram (a) "tenant + role seeding", LLD-2 (estate ->
 *     company/country), LLD-5 (seeding), LLD-1 (role catalog / the 3 NEW roles).
 *   - requirements.md R2.1-R2.7 (tenant seeding), R3.4 (ID row left unused),
 *     R4.1-R4.4 (role catalog + NEW roles).
 *
 * This service holds the whole behaviour so it is unit/behaviour testable; the
 * `fusion:seed-tenants` command is a thin wrapper that wires options/output and
 * calls {@see seed()}.
 *
 * ## What it seeds (idempotent upserts keyed by natural keys — R2/R4)
 *   1. m_country MY (prefix 1) + ID (prefix 2) by `code`                 (R2.1/R3.4)
 *   2. one m_company per distinct company_code, country_id = MY for all  (R2.2/R3.4)
 *   3. one m_company_config per company from a REPRESENTATIVE member
 *      estate's CI3 m_config; divergences across sibling estates logged  (R2.3/R2.4)
 *   4. the m_roles catalog, ensuring the 3 NEW roles exist               (R4.1-R4.4)
 *   5. one m_estate per source estate keyed by (company_id, estate_code) (R2.5)
 *   6. the estate_code -> {company_id, country_id} map via TenantResolver,
 *      persisted to the fusion_ledger for downstream stages              (R2.6)
 *
 * ## Resolution, never hard-coded (R2.7)
 * company_id for configs/estates is read back from m_company by company_code
 * AFTER the company upsert; country_id is read back from m_country by code.
 * No numeric id is assumed.
 *
 * ## Target-column safety
 * m_company_config / m_estate column lists are discovered live from
 * information_schema and only existing columns are written, so the seeder
 * tolerates schema drift between the probe and runtime.
 */
class TenantRoleSeeder
{
    /** Target connection name (SQL Server EPMS via ODBC). */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /** The single country every estate resolves to (R3.2/R3.4). */
    public const COUNTRY_MY = 'MY';

    /** The pre-existing ID country row — seeded but left unused (R3.4). */
    public const COUNTRY_ID = 'ID';

    /** role_code of the global tier seeded by provisionSuperAdmin (LLD-6/R9.1). */
    public const ROLE_SUPER_ADMIN = 'super_admin';

    /** role_code of the country tier seeded by provisionCountryAdmins (LLD-6/R9.3). */
    public const ROLE_COUNTRY_ADMIN = 'country_admin';

    /** Default super_admin username when the operator provides none (Open Decision A1). */
    public const DEFAULT_SUPER_ADMIN_USERNAME = 'superadmin';

    /**
     * Country catalog to upsert by `code` (R2.1). MY prefix 1, ID prefix 2.
     *
     * @var array<int, array{code:string, name:string, prefix:string}>
     */
    private const COUNTRIES = [
        ['code' => 'MY', 'name' => 'Malaysia', 'prefix' => '1'],
        ['code' => 'ID', 'name' => 'Indonesia', 'prefix' => '2'],
    ];

    /**
     * Human role names for the 3 NEW roles (R4.2-R4.4). Levels come from
     * config('fusion.new_roles') (single source of truth shared with RoleMap).
     *
     * @var array<string, string>
     */
    private const NEW_ROLE_NAMES = [
        'safety_officer'       => 'Safety Officer',
        'sustainability_clerk' => 'Sustainability Clerk',
        'ramp_tractor'         => 'Ramp - Tractor',
    ];

    private readonly ConnectionInterface $target;

    public function __construct(
        private readonly SourceRegistry $registry,
        private readonly TenantResolver $resolver,
        ?ConnectionInterface $target = null,
    ) {
        $this->target = $target ?? DB::connection(config('fusion.target_connection', self::TARGET_CONNECTION));
    }

    /**
     * Run the full seeding flow and return a structured summary.
     *
     * Progress is reported through the optional $progress callback (the command
     * passes one that writes to the console) so the service has no console I/O
     * dependency and stays testable.
     *
     * Admin-tier provisioning (LLD-6 / R9) runs LAST, after roles + countries
     * exist, so role_id / country_id resolve by code. The super_admin is ALWAYS
     * provisioned when $admin credentials are supplied; country_admins only when
     * $admin['seed_country_admins'] is true. Nothing here maps a source user —
     * these tiers are seeded, never migrated (R9.4).
     *
     * @param  callable(string $level, string $message):void|null  $progress
     * @param  array{
     *     super_admin?: array{username:string, email:string, password:string},
     *     seed_country_admins?: bool,
     *     country_admins?: array<string, array{username?:string, email?:string, password:string}>
     * }  $admin  resolved admin credentials/flags (the command sources these
     *            securely; the seeder stays console-free and testable).
     * @return array{
     *     countries:int, companies:int, configs:int, roles:int, newRoles:int,
     *     estates:int, divergences:array<int, array{company_code:string, estate_code:string, field:string, representative:mixed, other:mixed}>,
     *     ledgerPersisted:bool,
     *     superAdmin: array{username:string, email:string, user_id:int, role_id:int, created:bool}|null,
     *     countryAdmins: array<int, array{country:string, username:string, email:string, user_id:int, role_id:int, country_id:int, created:bool}>
     * }
     */
    public function seed(?callable $progress = null, array $admin = []): array
    {
        $say = $progress ?? static fn (string $level, string $message) => null;

        $say('info', 'Seeding countries (MY, ID) by code...');
        $countries = $this->seedCountries($say);

        $say('info', 'Seeding companies (one per distinct company_code, country_id = MY)...');
        $companies = $this->seedCompanies($say);

        $say('info', 'Seeding company configs from a representative member estate...');
        [$configs, $divergences] = $this->seedCompanyConfigs($say);

        $say('info', 'Seeding role catalog (ensuring the 3 NEW roles)...');
        [$roles, $newRoles] = $this->seedRoles($say);

        $say('info', 'Seeding estates (keyed by company_id + estate_code)...');
        $estates = $this->seedEstates($say);

        // R2.6 — build and persist the estate -> {company_id, country_id} map.
        $say('info', 'Building + persisting the tenant resolution map (R2.6)...');
        $ledgerPersisted = $this->buildAndPersistResolution($say);

        // R9.1-R9.3 — admin-tier provisioning runs AFTER roles + countries so
        // role_id / country_id resolve by code. super_admin is always seeded;
        // country_admins only under the operator flag.
        $superAdmin = null;
        $countryAdmins = [];

        if (! empty($admin['super_admin'])) {
            $say('info', 'Provisioning super_admin seed account (R9.1/R9.2)...');
            $superAdmin = $this->provisionSuperAdmin(
                username: $admin['super_admin']['username'],
                email: $admin['super_admin']['email'],
                plaintextPassword: $admin['super_admin']['password'],
                say: $say,
            );
        }

        if (! empty($admin['seed_country_admins']) && ! empty($admin['country_admins'])) {
            $say('info', 'Provisioning country_admin seed accounts (R9.3, --seed-country-admins)...');
            $countryAdmins = $this->provisionCountryAdmins($admin['country_admins'], $say);
        }

        return [
            'countries'       => $countries,
            'companies'       => $companies,
            'configs'         => $configs,
            'roles'           => $roles,
            'newRoles'        => $newRoles,
            'estates'         => $estates,
            'divergences'     => $divergences,
            'ledgerPersisted' => $ledgerPersisted,
            'superAdmin'      => $superAdmin,
            'countryAdmins'   => $countryAdmins,
        ];
    }

    /**
     * Upsert the m_country catalog by `code` (R2.1). Idempotent: re-running
     * leaves the rows unchanged except for updated_at.
     */
    public function seedCountries(?callable $say = null): int
    {
        $say ??= static fn (string $level, string $message) => null;
        $now = now();
        $count = 0;

        foreach (self::COUNTRIES as $country) {
            $this->target->table('m_country')->updateOrInsert(
                ['code' => $country['code']],
                [
                    'name'       => $country['name'],
                    'prefix'     => $country['prefix'],
                    'is_active'  => 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Upsert one m_company per distinct company_code (R2.2). All companies get
     * country_id = MY's id (R3.4 — the ID row is left unused). company_id is
     * never hard-coded; country_id is read back by code.
     */
    public function seedCompanies(?callable $say = null): int
    {
        $say ??= static fn (string $level, string $message) => null;
        $now = now();

        $myId = $this->countryIdByCode(self::COUNTRY_MY);

        $count = 0;
        foreach ($this->distinctCompanies() as $companyCode => $companyName) {
            $this->target->table('m_company')->updateOrInsert(
                ['company_code' => $companyCode],
                [
                    'country_id'   => $myId,
                    'company_name' => $companyName,
                    'is_active'    => 1,
                    'updated_at'   => $now,
                    'created_at'   => $now,
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Upsert one m_company_config per company (R2.3), sourced from a
     * representative member estate's CI3 m_config, logging config divergences
     * across the other member estates (R2.4) without failing.
     *
     * Only target columns that actually exist are written (schema-drift safe).
     *
     * @return array{0:int, 1:array<int, array{company_code:string, estate_code:string, field:string, representative:mixed, other:mixed}>}
     */
    public function seedCompanyConfigs(?callable $say = null): array
    {
        $say ??= static fn (string $level, string $message) => null;
        $now = now();

        $targetCols = $this->targetColumns('m_company_config');
        $configCount = 0;
        $divergences = [];

        foreach ($this->companyToEstateDescriptors() as $companyCode => $descriptors) {
            // The representative estate is the FIRST manifest entry for the company.
            $representative = $descriptors[0];
            $repConfig = $this->readSourceConfig($representative->dbName, $say);

            if ($repConfig === null) {
                $say('warn', sprintf(
                    '  %s: representative estate %s (%s) has no m_config row — config skipped.',
                    $companyCode, $representative->estateCode, $representative->dbName,
                ));
                continue;
            }

            $companyId = $this->companyIdByCode($companyCode);

            // Map the representative source config to the target config shape,
            // keeping only columns that exist in the live target table.
            $values = $this->mapConfigToTarget($repConfig, $companyId, $targetCols, $now);

            $this->target->table('m_company_config')->updateOrInsert(
                ['company_id' => $companyId],
                $values,
            );
            $configCount++;

            $say('info', sprintf(
                '  %s: config from representative estate %s (%s).',
                $companyCode, $representative->estateCode, $representative->dbName,
            ));

            // Log divergences for the OTHER member estates (R2.4). Multi-estate
            // companies: 1PIP(SG,BS,SM), 1PPP(PB,UJ), 1LUD(UF,UT), 1PDP(BD,GE).
            $siblings = array_slice($descriptors, 1);
            foreach ($siblings as $sibling) {
                $sibConfig = $this->readSourceConfig($sibling->dbName, $say);
                if ($sibConfig === null) {
                    $say('warn', sprintf(
                        '  %s: sibling estate %s (%s) has no m_config row — divergence check skipped.',
                        $companyCode, $sibling->estateCode, $sibling->dbName,
                    ));
                    continue;
                }

                $diffs = $this->compareConfigs($repConfig, $sibConfig);
                foreach ($diffs as $field => $pair) {
                    $divergences[] = [
                        'company_code'   => $companyCode,
                        'estate_code'    => $sibling->estateCode,
                        'field'          => $field,
                        'representative' => $pair['rep'],
                        'other'          => $pair['other'],
                    ];
                    $say('warn', sprintf(
                        '  DIVERGENCE %s/%s: %s = %s (rep %s/%s = %s)',
                        $companyCode, $sibling->estateCode, $field,
                        $this->scalarForLog($pair['other']),
                        $companyCode, $representative->estateCode,
                        $this->scalarForLog($pair['rep']),
                    ));
                    $this->logLedgerNote(
                        companyId: $companyId,
                        key: sprintf('config_divergence|%s|%s|%s', $companyCode, $sibling->estateCode, $field),
                        message: sprintf(
                            'config divergence %s/%s field=%s rep(%s)=%s other=%s',
                            $companyCode, $sibling->estateCode, $field,
                            $representative->estateCode, $this->scalarForLog($pair['rep']),
                            $this->scalarForLog($pair['other']),
                        ),
                    );
                }
            }
        }

        SourceConnection::close();

        return [$configCount, $divergences];
    }

    /**
     * Upsert the m_roles catalog (R4.1) and ensure the 3 NEW roles exist with
     * their configured levels (R4.2-R4.4). Returns [totalUpserted, newAdded].
     *
     * The catalog is the union of:
     *   - every role_code referenced by the LOCKED role_map (config('fusion.role_map')),
     *   - the 3 NEW roles (config('fusion.new_roles')),
     * resolved to a level via RoleMap (single source of truth). Each is upserted
     * by role_code; the SQL Server identity id is never hard-coded (R4.5).
     *
     * @return array{0:int, 1:int}
     */
    public function seedRoles(?callable $say = null): array
    {
        $say ??= static fn (string $level, string $message) => null;
        $now = now();

        $newRoles = (array) config('fusion.new_roles', []);
        $roleMap = (array) config('fusion.role_map', []);

        // Collect every role_code the fusion must guarantee exists.
        $roleCodes = array_values(array_unique(array_merge(
            array_values($roleMap),
            array_keys($newRoles),
        )));

        $total = 0;
        $newAdded = 0;

        foreach ($roleCodes as $roleCode) {
            $level = RoleMap::level($roleCode);
            if ($level === null) {
                $say('warn', "  role_code '{$roleCode}' has no known level — skipped.");
                continue;
            }

            $isNew = array_key_exists($roleCode, $newRoles);

            $exists = $this->target->table('m_roles')
                ->whereRaw('UPPER(role_code) = ?', [strtoupper($roleCode)])
                ->exists();

            $values = [
                'level'      => $level,
                'is_active'  => 1,
                'updated_at' => $now,
            ];

            // Only set role_name on insert / for the NEW roles, so an existing
            // human-curated role_name for catalog roles is not clobbered.
            $insertValues = $values + [
                'role_name'  => $this->roleNameFor($roleCode),
                'created_at' => $now,
            ];

            if ($exists) {
                // Keep existing role_name; just ensure level/is_active are correct.
                $this->target->table('m_roles')
                    ->whereRaw('UPPER(role_code) = ?', [strtoupper($roleCode)])
                    ->update($values);
            } else {
                $this->target->table('m_roles')->insert(
                    ['role_code' => $roleCode] + $insertValues
                );
                if ($isNew) {
                    $newAdded++;
                    $say('info', "  ADDED new role '{$roleCode}' (level {$level}).");
                }
            }

            $total++;
        }

        return [$total, $newAdded];
    }

    /**
     * Upsert one m_estate per source estate keyed by (company_id, estate_code)
     * (R2.5), with estate_name from the source m_config. company_id resolved by
     * company_code. Only existing target columns are written.
     */
    public function seedEstates(?callable $say = null): int
    {
        $say ??= static fn (string $level, string $message) => null;
        $now = now();

        $targetCols = $this->targetColumns('m_estate');
        $count = 0;

        foreach ($this->registry->all() as $descriptor) {
            $companyId = $this->companyIdByCode($descriptor->companyCode);
            $sourceConfig = $this->readSourceConfig($descriptor->dbName, $say);

            // estate_name / plant_code from source m_config when available; fall
            // back to the manifest estate_code if the source row is missing.
            $estateName = $sourceConfig['estate_name'] ?? $descriptor->estateCode;
            $plantCode = $sourceConfig['plant_code'] ?? null;

            $values = [
                'estate_name' => (string) $estateName,
                'updated_at'  => $now,
                'created_at'  => $now,
            ];
            if (in_array('estate_plant_code', $targetCols, true) && $plantCode !== null) {
                $values['estate_plant_code'] = (string) $plantCode;
            }

            // Keep only columns the target actually has (schema-drift safe).
            $values = array_intersect_key($values, array_flip($targetCols));

            $this->target->table('m_estate')->updateOrInsert(
                ['company_id' => $companyId, 'estate_code' => $descriptor->estateCode],
                $values,
            );
            $count++;
        }

        SourceConnection::close();

        return $count;
    }

    /**
     * Build the estate -> {company_id, country_id, company_code} resolution map
     * via TenantResolver and persist it to the fusion_ledger (R2.6). Returns
     * whether the ledger persist succeeded (best-effort: a missing ledger table
     * is logged but does not fail seeding — the map is still built in memory).
     */
    public function buildAndPersistResolution(?callable $say = null): bool
    {
        $say ??= static fn (string $level, string $message) => null;

        $this->resolver->buildFromRegistry($this->registry);

        if (! $this->target->getSchemaBuilder()->hasTable('fusion_ledger')) {
            $say('warn', 'fusion_ledger table is missing — resolution map built in memory but NOT persisted. '
                .'Run the fusion_ledger migration to enable downstream caching (R2.6).');

            return false;
        }

        $this->resolver->persistToLedger();
        $say('info', 'Resolution map persisted to fusion_ledger (entry_type=tenant_map).');

        return true;
    }

    // ---------------------------------------------------------------------
    // Admin-tier provisioning (LLD-6 / R9.1-R9.3) — SEEDED, never mapped.
    // ---------------------------------------------------------------------

    /**
     * Provision the single `super_admin` seed account (R9.1/R9.2, LLD-6).
     *
     * CI3 single-estate DBs contain no global user, so the top tier is seeded
     * explicitly so cross-company/country login works post-migration. This is
     * PURE seeding — no source user is ever mapped here (R9.4).
     *
     * Creates/updates exactly:
     *   - one `tc_user` keyed by `username` (idempotent upsert), carrying a
     *     bcrypt-hashed password supplied by the caller (never persisted or
     *     logged in plaintext — R9.2);
     *   - exactly one `tc_user_access` row with role_id = (super_admin by
     *     role_code), `company_id` NULL, `country_id` NULL, `estate_code` NULL,
     *     `is_active` = 1;
     *   - NO `tc_user_scope` row.
     *
     * The super_admin role_id is resolved by role_code against m_roles (never
     * hard-coded). The password is already plaintext here; it is hashed with
     * bcrypt via Hash::make before it touches the row. The caller is
     * responsible for sourcing the plaintext securely (option/env/prompt/
     * generated) and for the one-time display of any generated value.
     *
     * @param  string  $plaintextPassword  the operator-sourced password (R9.2).
     * @param  callable(string $level, string $message):void|null  $say
     * @return array{username:string, email:string, user_id:int, role_id:int, created:bool}
     */
    public function provisionSuperAdmin(
        string $username,
        string $email,
        string $plaintextPassword,
        ?callable $say = null,
    ): array {
        $say ??= static fn (string $level, string $message) => null;

        $roleId = $this->roleIdByCode(self::ROLE_SUPER_ADMIN);
        if ($roleId === null) {
            throw new \RuntimeException(sprintf(
                "role_code '%s' is not present in m_roles — seed roles before provisioning the super_admin (run seed()/seedRoles() first).",
                self::ROLE_SUPER_ADMIN,
            ));
        }

        $userId = $this->upsertAdminUser(
            username: $username,
            email: $email,
            userName: 'Super Administrator',
            plaintextPassword: $plaintextPassword,
        );

        // Exactly one tc_user_access: null company + null country = super_admin,
        // no estate_code. No tc_user_scope row is created for this tier (R9.1).
        $created = $this->upsertUserAccess(
            userId: $userId,
            roleId: $roleId,
            countryId: null,
            companyId: null,
        );

        $say('info', sprintf(
            '  super_admin provisioned: user "%s" (id %d), access role_id %d, company_id NULL, country_id NULL, no estate scope.',
            $username, $userId, $roleId,
        ));

        return [
            'username' => $username,
            'email'    => $email,
            'user_id'  => $userId,
            'role_id'  => $roleId,
            'created'  => $created,
        ];
    }

    /**
     * Provision one `country_admin` seed account per country (R9.3, LLD-6).
     *
     * Only invoked when the operator passes `--seed-country-admins`. For each
     * provided country code it creates/updates:
     *   - one `tc_user` keyed by `username` (idempotent), bcrypt password;
     *   - exactly one `tc_user_access` with role_id = (country_admin by
     *     role_code), `country_id` = that country's id, `company_id` NULL,
     *     `estate_code` NULL, `is_active` = 1;
     *   - NO estate `tc_user_scope` row.
     *
     * This is pure seeding (R9.4). country_admin role_id and each country_id are
     * resolved by code against m_roles / m_country (never hard-coded).
     *
     * @param  array<string, array{username?:string, email?:string, password:string}>  $perCountryCreds
     *         map of country code (e.g. 'MY', 'ID') => credentials. The password
     *         is plaintext (hashed here); username/email default deterministically
     *         to countryadmin_<CODE> / countryadmin_<code>@epms.local when absent.
     * @param  callable(string $level, string $message):void|null  $say
     * @return array<int, array{country:string, username:string, email:string, user_id:int, role_id:int, country_id:int, created:bool}>
     */
    public function provisionCountryAdmins(array $perCountryCreds, ?callable $say = null): array
    {
        $say ??= static fn (string $level, string $message) => null;

        $roleId = $this->roleIdByCode(self::ROLE_COUNTRY_ADMIN);
        if ($roleId === null) {
            throw new \RuntimeException(sprintf(
                "role_code '%s' is not present in m_roles — seed roles before provisioning country_admins (run seed()/seedRoles() first).",
                self::ROLE_COUNTRY_ADMIN,
            ));
        }

        $results = [];
        foreach ($perCountryCreds as $countryCode => $creds) {
            $countryCode = strtoupper((string) $countryCode);
            $countryId = $this->countryIdByCode($countryCode);
            if ($countryId === 0) {
                $say('warn', sprintf(
                    '  country_admin skipped: country code "%s" is not seeded in m_country.',
                    $countryCode,
                ));
                continue;
            }

            $username = $creds['username'] ?? ('countryadmin_'.$countryCode);
            $email = $creds['email'] ?? ('countryadmin_'.strtolower($countryCode).'@epms.local');
            $plaintextPassword = (string) ($creds['password'] ?? '');
            if ($plaintextPassword === '') {
                $say('warn', sprintf('  country_admin "%s" skipped: no password supplied.', $username));
                continue;
            }

            $userId = $this->upsertAdminUser(
                username: $username,
                email: $email,
                userName: sprintf('Country Administrator (%s)', $countryCode),
                plaintextPassword: $plaintextPassword,
            );

            // Exactly one tc_user_access: country_id set, company_id NULL,
            // estate_code NULL; no estate tc_user_scope row (R9.3).
            $created = $this->upsertUserAccess(
                userId: $userId,
                roleId: $roleId,
                countryId: $countryId,
                companyId: null,
            );

            $say('info', sprintf(
                '  country_admin provisioned: user "%s" (id %d), access role_id %d, country_id %d (%s), company_id NULL, no estate scope.',
                $username, $userId, $roleId, $countryId, $countryCode,
            ));

            $results[] = [
                'country'    => $countryCode,
                'username'   => $username,
                'email'      => $email,
                'user_id'    => $userId,
                'role_id'    => $roleId,
                'country_id' => $countryId,
                'created'    => $created,
            ];
        }

        return $results;
    }

    /**
     * Idempotent upsert of an admin-tier tc_user keyed by `username`, returning
     * its id. The password is hashed with bcrypt (Hash::make) immediately; the
     * plaintext is never written to any column, log, or ledger (R9.2). Only
     * columns the live tc_user table actually has are written (schema-drift
     * safe). Re-running re-hashes the supplied password and refreshes the row.
     */
    private function upsertAdminUser(
        string $username,
        string $email,
        string $userName,
        string $plaintextPassword,
    ): int {
        $now = now();
        $cols = $this->targetColumns('tc_user');

        $values = array_intersect_key([
            'email'      => $email,
            'password'   => Hash::make($plaintextPassword),
            'user_name'  => $userName,
            'is_active'  => 1,
            'updated_at' => $now,
            'created_at' => $now,
        ], array_flip($cols));

        $this->target->table('tc_user')->updateOrInsert(
            ['username' => $username],
            $values,
        );

        return (int) $this->target->table('tc_user')
            ->where('username', $username)
            ->value('id');
    }

    /**
     * Idempotent upsert of the SINGLE tc_user_access row for an admin-tier user
     * (keyed by user_id — the target enforces one access row per user). Writes
     * country_id/company_id/estate_code exactly as given (nulls preserved to
     * encode the tier) and sets is_active = 1. Returns whether the row was newly
     * created (true) vs updated (false). Only existing columns are written.
     */
    private function upsertUserAccess(
        int $userId,
        int $roleId,
        ?int $countryId,
        ?int $companyId,
    ): bool {
        $now = now();
        $cols = $this->targetColumns('tc_user_access');

        $existed = $this->target->table('tc_user_access')
            ->where('user_id', $userId)
            ->exists();

        $values = array_intersect_key([
            'role_id'     => $roleId,
            'country_id'  => $countryId,
            'company_id'  => $companyId,
            'estate_code' => null,
            'is_active'   => 1,
            'updated_at'  => $now,
            'created_at'  => $now,
        ], array_flip($cols));

        $this->target->table('tc_user_access')->updateOrInsert(
            ['user_id' => $userId],
            $values,
        );

        return ! $existed;
    }

    /**
     * Read-back m_roles.id by role_code (never hard-coded — R4.5/R9), or null
     * when the role has not been seeded yet.
     */
    private function roleIdByCode(string $roleCode): ?int
    {
        $id = $this->target->table('m_roles')
            ->whereRaw('UPPER(role_code) = ?', [strtoupper($roleCode)])
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Distinct company_code => company_name from the manifest (R2.2).
     *
     * @return array<string, string>
     */
    private function distinctCompanies(): array
    {
        $companies = [];
        foreach ($this->registry->all() as $descriptor) {
            // First manifest entry for a company_code wins the name.
            $companies[$descriptor->companyCode] ??= $descriptor->companyName;
        }

        return $companies;
    }

    /**
     * company_code => ordered list of its SourceDescriptors (manifest order).
     * The first entry is the representative estate (R2.4).
     *
     * @return array<string, array<int, SourceDescriptor>>
     */
    private function companyToEstateDescriptors(): array
    {
        $map = [];
        foreach ($this->registry->all() as $descriptor) {
            $map[$descriptor->companyCode][] = $descriptor;
        }

        return $map;
    }

    /**
     * Read a source estate's m_config as an associative array, or null when the
     * DB has no m_config row. The source is opened on demand; the caller closes
     * the connection after finishing a batch of reads.
     *
     * @param  callable(string $level, string $message):void  $say
     * @return array<string, mixed>|null
     */
    private function readSourceConfig(string $dbName, callable $say): ?array
    {
        try {
            $pg = SourceConnection::for($dbName);
            $row = $pg->table('m_config')->first();

            if ($row === null) {
                return null;
            }

            return array_change_key_case((array) $row, CASE_LOWER);
        } catch (\Throwable $e) {
            $say('warn', sprintf('  could not read m_config from %s: %s', $dbName, $e->getMessage()));

            return null;
        }
    }

    /**
     * Map a source m_config row to the target m_company_config insert/update
     * payload, keeping only columns that exist in the live target table and
     * supplying safe defaults for the target's NOT NULL columns (R2.3).
     *
     * @param  array<string, mixed>  $src       lower-cased source m_config row.
     * @param  array<int, string>    $targetCols live target column names.
     * @param  array<string, mixed>  (returned)  payload for updateOrInsert.
     * @return array<string, mixed>
     */
    private function mapConfigToTarget(array $src, int $companyId, array $targetCols, \DateTimeInterface $now): array
    {
        // Full candidate payload keyed by TARGET column name. Only the subset
        // present in $targetCols is actually written.
        $candidate = [
            'company_id'                      => $companyId,
            'profile_code'                    => $src['profile_code'] ?? null,
            'profile_name'                    => $src['profile_name'] ?? null,
            'estate_code'                     => $src['estate_code'] ?? null,
            'estate_name'                     => $src['estate_name'] ?? null,
            'plant_code'                      => $src['plant_code'] ?? null,
            'sap_client'                      => $src['sap_client'] ?? null,
            'system_is_palm'                  => $this->boolInt($src['system_is_palm'] ?? false),
            'system_is_coconut'               => $this->boolInt($src['system_is_coconut'] ?? false),
            'system_is_rubber'                => $this->boolInt($src['system_is_rubber'] ?? false),
            'system_is_durian'                => $this->boolInt($src['system_is_durian'] ?? false),
            'integration_type'                => (int) ($src['integration_type'] ?? 0),
            'have_internet_connection'        => $this->boolInt($src['have_internet_connection'] ?? false),
            'sap_api_url'                     => $src['sap_api_url'] ?? null,
            'sap_user_id'                     => $src['sap_user_id'] ?? null,
            'sap_password'                    => $src['sap_password'] ?? null,
            'cutter_distribution_value'       => $src['cutter_distribution_value'] ?? null,
            'carrier_distribution_value'      => $src['carrier_distribution_value'] ?? null,
            'cutter_lf_distribution_value'    => $src['cutter_lf_distribution_value'] ?? null,
            'carrier_lf_distribution_value'   => $src['carrier_lf_distribution_value'] ?? null,
            'attendance_default_value'        => $src['attendance_default_value'] ?? null,
            'attendance_normal_default_value' => $src['attendance_normal_default_value'] ?? null,
            // Source misspells the column ("attandance"); map it to the target.
            'allowed_attendance_codes'        => $src['allowed_attandance_codes_for_work_assignment']
                                                  ?? ($src['allowed_attendance_codes'] ?? null),
            'daily_overtime_max_limit'        => (int) ($src['daily_overtime_max_limit'] ?? 0),
            'max_oph_restan'                  => (int) ($src['max_oph_restan'] ?? 0),
            'fdn_oph'                         => $this->boolInt($src['fdn_oph'] ?? false),
            'is_fixed_platform'               => $this->boolInt($src['is_fixed_platform'] ?? false),
            'is_lock_system'                  => $this->boolInt($src['is_lock_system'] ?? false),
            'additional_settings'             => $this->jsonString($src['additional_settings'] ?? null),
            // Source m_config has no hse_api_url; left null unless present.
            'hse_api_url'                     => $src['hse_api_url'] ?? null,
            'created_by'                      => $src['created_by'] ?? null,
            'updated_by'                      => $src['updated_by'] ?? null,
            'created_at'                      => $now,
            'updated_at'                      => $now,
        ];

        // Keep only columns the live target table actually has. company_id is
        // the natural key passed separately to updateOrInsert, so drop it here.
        $payload = array_intersect_key($candidate, array_flip($targetCols));
        unset($payload['company_id']);

        return $payload;
    }

    /**
     * Compare two source m_config rows on the config-relevant fields and return
     * the fields that differ (R2.4). Audit and identity columns are ignored
     * (they legitimately differ per estate).
     *
     * @param  array<string, mixed>  $rep   lower-cased representative config.
     * @param  array<string, mixed>  $other lower-cased sibling config.
     * @return array<string, array{rep:mixed, other:mixed}>
     */
    private function compareConfigs(array $rep, array $other): array
    {
        $fields = [
            'plant_code', 'sap_api_url', 'sap_user_id', 'sap_password',
            'integration_type', 'have_internet_connection',
            'system_is_palm', 'system_is_coconut', 'system_is_rubber', 'system_is_durian',
            'is_lock_system', 'is_fixed_platform', 'fdn_oph',
            'daily_overtime_max_limit', 'max_oph_restan',
            'attendance_default_value', 'attendance_normal_default_value',
            'additional_settings',
        ];

        $diffs = [];
        foreach ($fields as $field) {
            $repVal = $this->normalizeForCompare($rep[$field] ?? null);
            $otherVal = $this->normalizeForCompare($other[$field] ?? null);
            if ($repVal !== $otherVal) {
                $diffs[$field] = ['rep' => $rep[$field] ?? null, 'other' => $other[$field] ?? null];
            }
        }

        return $diffs;
    }

    /** Normalize a config value to a comparable scalar (bool/json aware). */
    private function normalizeForCompare(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value) ?: '';
        }

        return (string) ($value ?? '');
    }

    /** Short scalar rendering for a log line. */
    private function scalarForLog(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return '[json]';
        }
        $s = (string) ($value ?? 'null');

        return mb_strlen($s) > 60 ? mb_substr($s, 0, 57).'...' : $s;
    }

    /** Coerce a source boolean/smallint flag to a SQL Server bit (0/1). */
    private function boolInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return ((int) $value) !== 0 ? 1 : 0;
    }

    /** Render a json column value as a string for an nvarchar target column. */
    private function jsonString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return json_encode($value) ?: null;
        }

        return (string) $value;
    }

    /** A human role_name for a role_code (NEW roles get a friendly label). */
    private function roleNameFor(string $roleCode): string
    {
        if (array_key_exists($roleCode, self::NEW_ROLE_NAMES)) {
            return self::NEW_ROLE_NAMES[$roleCode];
        }

        // Fallback: title-case the code (e.g. estate_manager -> Estate Manager).
        return ucwords(str_replace('_', ' ', $roleCode));
    }

    /**
     * Record a non-fatal note (e.g. a config divergence) to the ledger.
     * Idempotent: keyed by (entry_type=note, key_text) so a re-run updates the
     * existing note rather than appending a duplicate.
     */
    private function logLedgerNote(int $companyId, string $key, string $message): void
    {
        if (! $this->target->getSchemaBuilder()->hasTable('fusion_ledger')) {
            return;
        }

        $now = now();
        $this->target->table('fusion_ledger')->updateOrInsert(
            ['entry_type' => 'note', 'key_text' => $key],
            [
                'company_id' => $companyId,
                'message'    => $message,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /** Read-back m_country.id by code (never hard-coded — R2.7). */
    private function countryIdByCode(string $code): int
    {
        return (int) $this->target->table('m_country')
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->value('id');
    }

    /** Read-back m_company.id by company_code (never hard-coded — R2.7). */
    private function companyIdByCode(string $companyCode): int
    {
        return (int) $this->target->table('m_company')
            ->whereRaw('UPPER(company_code) = ?', [strtoupper($companyCode)])
            ->value('id');
    }

    /**
     * Live column names of a target table (lower-cased), for schema-drift-safe
     * writes.
     *
     * @return array<int, string>
     */
    private function targetColumns(string $table): array
    {
        $rows = $this->target->select(
            'SELECT column_name FROM information_schema.columns WHERE table_name = ?',
            [$table],
        );

        $cols = [];
        foreach ($rows as $row) {
            $arr = array_change_key_case((array) $row, CASE_LOWER);
            if (isset($arr['column_name'])) {
                $cols[] = strtolower((string) $arr['column_name']);
            }
        }

        return $cols;
    }
}
