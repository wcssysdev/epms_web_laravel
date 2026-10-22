<?php

namespace App\Console\Commands\Fusion;

use App\Services\Fusion\RbacMigrator;
use Illuminate\Console\Command;

/**
 * `fusion:migrate-rbac` — the RBAC migration stage of estate-db-fusion
 * (spec task 5, the CENTERPIECE).
 *
 * Spec authority: design.md sequence diagram (b) "user migration with scope
 * assignment", LLD-1 (LOCKED role map), LLD-6 (seed-only tiers), LLD-7
 * (cross-estate dedup), LLD-9 (idempotency), LLD-10 (migrate pseudocode);
 * requirements.md R5/R6/R7/R8/R9.4/R14.
 *
 * This command is a THIN WRAPPER: it wires console output + run-constraint
 * options and delegates every behavioural decision to
 * {@see RbacMigrator::migrate()} so the logic is unit/behaviour testable.
 *
 * ## What task 5.3 does through this command (identity migration only)
 *   - iterates the 12 source DBs via SourceRegistry;
 *   - SELECTs ACTIVE tc_user rows (defensive active-flag detection);
 *   - resolves role_code via the LOCKED map, recording UNMAPPED user_role
 *     values to the error ledger and SKIPPING those users (never guessed);
 *   - skips users with no dedup identity (recorded to the error ledger);
 *   - raw-inserts tc_user carrying the $2y$12$ bcrypt hash VERBATIM (no rehash,
 *     no reset) and records the DedupKey -> user_id mapping (entry_type
 *     'user_dedup') for idempotency;
 *   - calls the per-user extension seams assignAccess()/assignScopes() that
 *     later tasks 5.5/5.6 fill.
 *
 * Tiered tc_user_access (5.5), additive tc_user_scope (5.6), cross-estate dedup
 * conflict handling (5.8), and full idempotency (5.10) EXTEND the same
 * RbacMigrator service at its documented extension points and are all now
 * applied — the summary surfaces the idempotency counters (scopes applied /
 * skipped, access unchanged).
 *
 * ## Prerequisites
 *   Run `fusion:seed-tenants` first — this stage resolves company/country ids
 *   and role ids by CODE against the seeded tenant/role tables. A source DB
 *   whose estate is not seeded is recorded to the error ledger and skipped.
 *
 * ## Re-run safety (task 5.10 — full idempotency IMPLEMENTED)
 *   The command consults the user_dedup ledger before inserting (no duplicate
 *   users) AND the scope_applied ledger before writing an estate scope (no-op
 *   when already applied). A clean re-run is observably a no-op: the summary
 *   reports 0 users inserted and 0 new estate scopes applied (all skipped), and
 *   fusion_ledger does not grow because every ledger write is a stable-key
 *   upsert. This command NEVER truncates.
 *
 * ## Guarded validation options
 *   --only-db=<name>  restrict the run to a single source db_name.
 *   --limit=<n>       cap the source rows read per DB (a few-rows smoke run).
 */
class MigrateRbacCommand extends Command
{
    protected $signature = 'fusion:migrate-rbac
        {--only-db= : Restrict the run to a single source db_name (guarded validation run).}
        {--limit= : Cap the number of source tc_user rows read per DB (guarded few-rows run).}';

    protected $description = 'Migrate CI3 tc_user identities into the multi-tenant EPMS tc_user, carrying the bcrypt hash verbatim; resolve roles via the LOCKED map (skip+ledger unmapped); record DedupKey->user_id for idempotency. Access/scope/dedup-conflict/full-idempotency are later tasks. Never truncates; safe to re-run.';

    public function handle(RbacMigrator $migrator): int
    {
        $this->info('fusion:migrate-rbac — RBAC identity migration (task 5.3). No truncate.');
        $this->warn('Prerequisite: run `php artisan fusion:seed-tenants` first (tenant/role ids are resolved by code).');
        $this->newLine();

        $options = [
            'only_db'      => $this->normalizedOption('only-db'),
            'limit_per_db' => $this->intOption('limit'),
        ];

        if ($options['only_db'] !== null) {
            $this->line('Restricting run to source DB: '.$options['only_db']);
        }
        if ($options['limit_per_db'] !== null) {
            $this->line('Limiting to '.$options['limit_per_db'].' source row(s) per DB.');
        }

        try {
            $summary = $migrator->migrate(
                progress: fn (string $level, string $message) => $this->emit($level, $message),
                options: $options,
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('RBAC migration failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->renderSummary($summary);

        return self::SUCCESS;
    }

    /** Map a migrator progress level to the matching console writer. */
    private function emit(string $level, string $message): void
    {
        match ($level) {
            'error' => $this->error($message),
            'warn' => $this->warn($message),
            default => $this->line($message),
        };
    }

    /** A trimmed string option, or null when absent/blank. */
    private function normalizedOption(string $name): ?string
    {
        $value = $this->option($name);
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** A positive int option, or null when absent/invalid. */
    private function intOption(string $name): ?int
    {
        $raw = $this->normalizedOption($name);
        if ($raw === null || ! ctype_digit($raw)) {
            return null;
        }
        $n = (int) $raw;

        return $n > 0 ? $n : null;
    }

    /**
     * Print the final migration summary + the active-flag detection report.
     *
     * @param  array{databases:int, usersRead:int, usersInserted:int, usersExisting:int, skippedInactive:int, skippedUnmapped:int, skippedNoIdentity:int, skippedNoEmail:int, errors:int, notes:int, scopesApplied:int, scopesSkipped:int, accessUnchanged:int, activeFlagSamples:array<string,int>}  $summary
     */
    private function renderSummary(array $summary): void
    {
        $this->newLine();
        $this->info('RBAC migration summary:');
        $this->table(
            ['Item', 'Count'],
            [
                ['Source DBs processed', $summary['databases']],
                ['Users read', $summary['usersRead']],
                ['Users inserted (new identities)', $summary['usersInserted']],
                ['Users existing (dedup / re-run)', $summary['usersExisting']],
                ['Estate scopes applied (written this run)', $summary['scopesApplied'] ?? 0],
                ['Estate scopes skipped (already applied — no-op)', $summary['scopesSkipped'] ?? 0],
                ['Access rows unchanged (already present)', $summary['accessUnchanged'] ?? 0],
                ['Skipped — inactive', $summary['skippedInactive']],
                ['Skipped — unmapped role', $summary['skippedUnmapped']],
                ['Skipped — no identity', $summary['skippedNoIdentity']],
                ['Skipped — no email (target email NOT NULL)', $summary['skippedNoEmail'] ?? 0],
                ['Error-ledger entries this run', $summary['errors']],
            ],
        );

        // [task 5.10] Spell out the idempotency verdict so an operator can SEE a
        // re-run was a no-op without diffing the DB: a clean re-run inserts 0
        // new users and writes 0 new estate scopes (all scopes short-circuited).
        if (($summary['usersInserted'] ?? 0) === 0 && ($summary['scopesApplied'] ?? 0) === 0) {
            $this->info('Idempotent re-run: 0 new users, 0 new estate scopes — this run was a clean no-op (R14.2/R14.5).');
        } else {
            $this->line(sprintf(
                'This run applied %d new user(s) and %d new estate scope(s); %d scope(s) were already applied (skipped).',
                $summary['usersInserted'] ?? 0,
                $summary['scopesApplied'] ?? 0,
                $summary['scopesSkipped'] ?? 0,
            ));
        }

        if (! empty($summary['activeFlagSamples'])) {
            $this->newLine();
            $this->info('Active-flag detection (how user_status values were interpreted):');
            $rows = [];
            foreach ($summary['activeFlagSamples'] as $flag => $count) {
                $rows[] = [$flag, $count];
            }
            $this->table(['user_status => interpretation', 'Count'], $rows);
        }

        $this->newLine();
        $this->info('Done. Identity migrated with password carried verbatim; tiered tc_user_access (5.5), '
            .'additive tc_user_scope (5.6), dedup-conflict (5.8) and idempotent re-run (5.10) all applied. '
            .'Safe to re-run — a second run is a clean no-op.');
    }
}
