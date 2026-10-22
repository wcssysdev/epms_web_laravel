<?php

namespace App\Console\Commands\Fusion;

use App\Services\Fusion\TenantRoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * `fusion:seed-tenants` — the tenant/role seeding stage of estate-db-fusion
 * (spec task 4.1).
 *
 * Spec authority: design.md sequence diagram (a) "tenant + role seeding",
 * LLD-5 (seeding) + LLD-1 (role catalog); requirements.md R2.1-R2.7, R3.4,
 * R4.1-R4.4.
 *
 * This command is a THIN WRAPPER: it wires console output and delegates every
 * behavioural decision to {@see TenantRoleSeeder::seed()} so the logic is
 * unit/behaviour testable.
 *
 * ## It performs idempotent upserts (safe to re-run), keyed by natural keys:
 *   - m_country MY(prefix 1) + ID(prefix 2) by `code`                    (R2.1/R3.4)
 *   - one m_company per company_code (country_id = MY for all)           (R2.2/R3.4)
 *   - one m_company_config per company from a representative estate's
 *     CI3 m_config; divergences across sibling estates are LOGGED        (R2.3/R2.4)
 *   - the m_roles catalog, ensuring safety_officer(60),
 *     sustainability_clerk(70), ramp_tractor(70)                         (R4.1-R4.4)
 *   - one m_estate per source estate keyed by (company_id, estate_code)  (R2.5)
 *   - the estate_code -> {company_id, country_id} map persisted to the
 *     fusion_ledger via TenantResolver                                   (R2.6)
 *
 * It also provisions the SEEDED admin tiers at the end of the run (spec task
 * 4.2 / LLD-6 / R9): one `super_admin` always, and one `country_admin` per
 * seeded country only when `--seed-country-admins` is passed. These tiers are
 * SEEDED, never mapped from a source user (R9.4).
 *
 * Password sourcing (R9.2 — never committed):
 *   super_admin password = --super-admin-password  (option)
 *                       || env FUSION_SUPER_ADMIN_PASSWORD
 *                       || secret prompt (interactive TTY)
 *                       || a generated strong password printed ONCE to the operator.
 *   country_admin password = --country-admin-password
 *                       || env FUSION_COUNTRY_ADMIN_PASSWORD
 *                       || secret prompt || generated-and-printed-once.
 * The plaintext is passed to the seeder which bcrypt-hashes it immediately; it
 * is never written to a column, log line, or ledger.
 *
 * It NEVER truncates and is NOT the safety stage; `fusion:prepare-target` is a
 * separate, explicitly-confirmed command. Re-running this command is safe.
 */
class SeedTenantsCommand extends Command
{
    protected $signature = 'fusion:seed-tenants
        {--seed-country-admins : Also provision one country_admin seed user per seeded country (R9.3).}
        {--super-admin-username= : Username for the super_admin seed account (default: superadmin).}
        {--super-admin-email= : Email for the super_admin seed account (default: <username>@epms.local).}
        {--super-admin-password= : Password for the super_admin (prefer env FUSION_SUPER_ADMIN_PASSWORD; prompted or generated if omitted). Never committed.}
        {--country-admin-password= : Password applied to every provisioned country_admin (prefer env FUSION_COUNTRY_ADMIN_PASSWORD; prompted or generated if omitted). Never committed.}';

    protected $description = 'Seed countries, companies, company configs, roles (incl. the 3 NEW roles), estates and the estate->tenant resolution map, then provision the super_admin (always) and optional country_admin seed users. Idempotent; never truncates.';

    public function handle(TenantRoleSeeder $seeder): int
    {
        $this->info('fusion:seed-tenants — idempotent tenant/role seeding (no truncate).');
        $this->newLine();

        try {
            $admin = $this->resolveAdminCredentials();
        } catch (\Throwable $e) {
            $this->error('Admin provisioning setup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $summary = $seeder->seed(
                progress: fn (string $level, string $message) => $this->emit($level, $message),
                admin: $admin,
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Seeding failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->renderSummary($summary);
        $this->renderAdminNotice();

        return self::SUCCESS;
    }

    /**
     * Resolve the admin-tier credentials to pass to the seeder, keeping all
     * password sourcing (option/env/secret-prompt/generated) in the command so
     * the seeder stays console-free (R9.2). Any generated password is stashed on
     * $this->generated* for a ONE-TIME display after seeding — never persisted.
     *
     * @return array{
     *     super_admin: array{username:string, email:string, password:string},
     *     seed_country_admins: bool,
     *     country_admins: array<string, array{username:string, email:string, password:string}>
     * }
     */
    private function resolveAdminCredentials(): array
    {
        $username = (string) ($this->option('super-admin-username')
            ?: TenantRoleSeeder::DEFAULT_SUPER_ADMIN_USERNAME);
        $email = (string) ($this->option('super-admin-email') ?: ($username.'@epms.local'));

        $superPassword = $this->resolvePassword(
            option: 'super-admin-password',
            env: 'FUSION_SUPER_ADMIN_PASSWORD',
            promptLabel: 'super_admin password',
            generatedSink: function (string $pw): void {
                $this->generatedSuperAdminPassword = $pw;
            },
        );

        $admin = [
            'super_admin' => [
                'username' => $username,
                'email'    => $email,
                'password' => $superPassword,
            ],
            'seed_country_admins' => (bool) $this->option('seed-country-admins'),
            'country_admins'      => [],
        ];

        if ($admin['seed_country_admins']) {
            $countryPassword = $this->resolvePassword(
                option: 'country-admin-password',
                env: 'FUSION_COUNTRY_ADMIN_PASSWORD',
                promptLabel: 'country_admin password (applied to every country_admin)',
                generatedSink: function (string $pw): void {
                    $this->generatedCountryAdminPassword = $pw;
                },
            );

            // One country_admin per country the fusion seeds (MY always; ID
            // present but unused by the fusion — still gets an account so the
            // tier is complete). Usernames/emails default deterministically in
            // the seeder; we only need to supply the shared password here.
            foreach ([TenantRoleSeeder::COUNTRY_MY, TenantRoleSeeder::COUNTRY_ID] as $code) {
                $admin['country_admins'][$code] = ['password' => $countryPassword];
            }
        }

        return $admin;
    }

    /**
     * Source one plaintext password per the R9.2 policy, in priority order:
     * CLI option -> env var -> secret prompt (interactive only) -> a generated
     * strong password (reported once via $generatedSink). The plaintext is
     * returned to the caller and never logged here.
     *
     * @param  callable(string):void  $generatedSink  receives a generated password for one-time display.
     */
    private function resolvePassword(string $option, string $env, string $promptLabel, callable $generatedSink): string
    {
        $fromOption = $this->option($option);
        if (is_string($fromOption) && $fromOption !== '') {
            return $fromOption;
        }

        $fromEnv = env($env);
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        // Interactive terminals get a hidden prompt; non-interactive runs fall
        // through to a generated-and-printed-once password (safe for the
        // non-production target).
        if ($this->input->isInteractive()) {
            $entered = (string) $this->secret("Enter the {$promptLabel} (leave blank to auto-generate)");
            if ($entered !== '') {
                return $entered;
            }
        }

        $generated = $this->generateStrongPassword();
        $generatedSink($generated);

        return $generated;
    }

    /** Generate a strong random password (never committed; printed once). */
    private function generateStrongPassword(): string
    {
        // 24 URL-safe chars + mixed classes to satisfy common policies.
        return Str::password(24, letters: true, numbers: true, symbols: true, spaces: false);
    }

    /** One-time plaintext of a generated super_admin password, if any. */
    private ?string $generatedSuperAdminPassword = null;

    /** One-time plaintext of a generated country_admin password, if any. */
    private ?string $generatedCountryAdminPassword = null;

    /**
     * Print any generated passwords ONCE so the operator can record them. These
     * are not persisted anywhere; they exist only for this single display.
     */
    private function renderAdminNotice(): void
    {
        if ($this->generatedSuperAdminPassword === null && $this->generatedCountryAdminPassword === null) {
            return;
        }

        $this->newLine();
        $this->warn('Generated admin password(s) — shown ONCE, not stored anywhere. Record them now:');
        if ($this->generatedSuperAdminPassword !== null) {
            $this->line('  super_admin   password: '.$this->generatedSuperAdminPassword);
        }
        if ($this->generatedCountryAdminPassword !== null) {
            $this->line('  country_admin password: '.$this->generatedCountryAdminPassword);
        }
        $this->warn('Store these in your secrets manager; they cannot be recovered after this run.');
    }

    /** Map a seeder progress level to the matching console writer. */
    private function emit(string $level, string $message): void
    {
        match ($level) {
            'error' => $this->error($message),
            'warn' => $this->warn($message),
            default => $this->line($message),
        };
    }

    /**
     * Print the final seeding summary table.
     *
     * @param  array{countries:int, companies:int, configs:int, roles:int, newRoles:int, estates:int, divergences:array<int, array{company_code:string, estate_code:string, field:string, representative:mixed, other:mixed}>, ledgerPersisted:bool, superAdmin:array{username:string, email:string, user_id:int, role_id:int, created:bool}|null, countryAdmins:array<int, array{country:string, username:string, email:string, user_id:int, role_id:int, country_id:int, created:bool}>}  $summary
     */
    private function renderSummary(array $summary): void
    {
        $superAdmin = $summary['superAdmin'] ?? null;
        $countryAdmins = $summary['countryAdmins'] ?? [];

        $this->newLine();
        $this->info('Seeding summary:');
        $this->table(
            ['Item', 'Count'],
            [
                ['Countries upserted', $summary['countries']],
                ['Companies upserted', $summary['companies']],
                ['Company configs upserted', $summary['configs']],
                ['Roles upserted', $summary['roles']],
                ['NEW roles added', $summary['newRoles']],
                ['Estates upserted', $summary['estates']],
                ['Config divergences logged', count($summary['divergences'])],
                ['Resolution map persisted', $summary['ledgerPersisted'] ? 'yes' : 'no (ledger missing)'],
                ['super_admin provisioned', $superAdmin
                    ? sprintf('%s (%s)', $superAdmin['username'], $superAdmin['created'] ? 'created' : 'updated')
                    : 'no'],
                ['country_admins provisioned', count($countryAdmins)],
            ],
        );

        if (! empty($countryAdmins)) {
            $this->newLine();
            $this->info('Country admins:');
            $this->table(
                ['Country', 'Username', 'country_id', 'State'],
                array_map(static fn (array $c): array => [
                    $c['country'], $c['username'], $c['country_id'], $c['created'] ? 'created' : 'updated',
                ], $countryAdmins),
            );
        }

        if (! empty($summary['divergences'])) {
            $this->newLine();
            $this->warn('Config divergences (representative vs sibling estate):');
            $this->table(
                ['Company', 'Estate', 'Field', 'Representative', 'Sibling'],
                array_map(static function (array $d): array {
                    $fmt = static function (mixed $v): string {
                        if (is_bool($v)) {
                            return $v ? 'true' : 'false';
                        }
                        if (is_array($v)) {
                            return '[json]';
                        }
                        $s = (string) ($v ?? 'null');

                        return mb_strlen($s) > 40 ? mb_substr($s, 0, 37).'...' : $s;
                    };

                    return [
                        $d['company_code'], $d['estate_code'], $d['field'],
                        $fmt($d['representative']), $fmt($d['other']),
                    ];
                }, $summary['divergences']),
            );
        }

        $this->newLine();
        $this->info('Done. This command is idempotent and safe to re-run.');
    }
}
