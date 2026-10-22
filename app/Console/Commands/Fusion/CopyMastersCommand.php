<?php

namespace App\Console\Commands\Fusion;

use App\Services\Fusion\MasterCopier;
use Illuminate\Console\Command;

/**
 * `fusion:copy-masters` — the master-data copy stage of estate-db-fusion
 * (spec task 7, requirements R11.1–R11.6).
 *
 * Spec authority: design.md LLD-3 (table categories), LLD-8 (PK/collision),
 * LLD-9 (idempotency), sequence diagram (c) "master copy"; requirements.md
 * R11 (master copy) and R13/R14 (cross-engine load, idempotent re-run).
 *
 * This command is a THIN WRAPPER: it wires console output + run-constraint
 * options and delegates every behavioural decision to
 * {@see MasterCopier::copy()} so the logic is unit/behaviour testable (mirrors
 * {@see MigrateRbacCommand}).
 *
 * ## What this stage does (task 7.1 — full master copy with tenant handling)
 *   - iterates the 12 source DBs via SourceRegistry;
 *   - classifies tables and processes ONLY GLOBAL_MASTER and TENANT_MASTER
 *     (res_data/audit_trail and every non-master category are skipped — R11.5);
 *   - GLOBAL masters: natural-key UPSERT, deduped across all 12 DBs (R11.2) —
 *     a second run inserts no duplicates;
 *   - TENANT masters: inject company_id (+ country_id where the target column
 *     exists), drop the source PK for a fresh identity, remap audit via the
 *     shared RowTransformer (R11.3/R11.4), and record a
 *     (company_id, business_key) -> new id ledger entry so later child copiers
 *     resolve the new identity and re-runs do not double-insert (R11.6/R14.3);
 *   - per-row coercion errors are recorded to the error ledger and the batch
 *     CONTINUES (R13.5); whole-table/DB failures are recorded and the stage
 *     moves on.
 *
 * ## Prerequisites
 *   Run `fusion:seed-tenants` first — this stage resolves company/country ids by
 *   CODE against the seeded tenant tables. A source DB whose estate is not
 *   seeded is recorded to the error ledger and skipped.
 *
 * ## Re-run safety (hardened + made observable in task 7.2 — R14.3/R14.5)
 *   GLOBAL upserts are naturally idempotent (business-key upsert); TENANT
 *   inserts consult the (company_id, business_key) ledger before inserting.
 *   This command NEVER truncates. The summary now splits GLOBAL new-vs-existing
 *   and prints a clear verdict ("Idempotent re-run: 0 new tenant masters, 0 new
 *   global masters") so a second full run is a PROVABLE, VISIBLE clean no-op
 *   that does not grow fusion_ledger.
 *
 * ## Guarded validation options (like fusion:migrate-rbac)
 *   --only-db=<name>  restrict the run to a single source db_name.
 *   --limit=<n>       cap the source rows read PER TABLE (a few-rows smoke run).
 */
class CopyMastersCommand extends Command
{
    protected $signature = 'fusion:copy-masters
        {--only-db= : Restrict the run to a single source db_name (guarded validation run).}
        {--limit= : Cap the number of source rows read per master table (guarded few-rows run).}';

    protected $description = 'Copy CI3 master data into multi-tenant EPMS: GLOBAL masters upserted by natural key (deduped across all 12 DBs); TENANT masters stamped with company_id, re-identitied, audit-remapped, with a (company_id, business_key)->new_id ledger entry. Full copy, no date filter. Never truncates; safe to re-run.';

    public function handle(MasterCopier $copier): int
    {
        $this->info('fusion:copy-masters — master data copy (task 7.1). Full copy, no date filter. No truncate.');
        $this->warn('Prerequisite: run `php artisan fusion:seed-tenants` first (tenant ids are resolved by code).');
        $this->newLine();

        $options = [
            'only_db' => $this->normalizedOption('only-db'),
            'limit'   => $this->intOption('limit'),
        ];

        if ($options['only_db'] !== null) {
            $this->line('Restricting run to source DB: '.$options['only_db']);
        }
        if ($options['limit'] !== null) {
            $this->line('Limiting to '.$options['limit'].' source row(s) per master table.');
        }

        try {
            $summary = $copier->copy(
                progress: fn (string $level, string $message) => $this->emit($level, $message),
                options: $options,
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Master copy failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->renderSummary($summary);

        return self::SUCCESS;
    }

    /** Map a copier progress level to the matching console writer. */
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
     * Print the final copy summary plus a per-table breakdown.
     *
     * @param  array{databases:int, tablesProcessed:int, tablesSkipped:int, globalsUpserted:int, globalInserted:int, globalExisting:int, tenantInserted:int, tenantExisting:int, skipped:int, errors:int, notes:int, perTable:array<string,array{category:string,read:int,written:int,existing:int,errors:int}>}  $summary
     */
    private function renderSummary(array $summary): void
    {
        $globalInserted = $summary['globalInserted'] ?? 0;
        $globalExisting = $summary['globalExisting'] ?? 0;
        $tenantInserted = $summary['tenantInserted'] ?? 0;
        $tenantExisting = $summary['tenantExisting'] ?? 0;

        $this->newLine();
        $this->info('Master copy summary:');
        $this->table(
            ['Item', 'Count'],
            [
                ['Source DBs processed', $summary['databases']],
                ['Master tables processed', $summary['tablesProcessed']],
                ['Master tables skipped (ambiguous key / no target)', $summary['tablesSkipped']],
                ['GLOBAL rows upserted (deduped across DBs)', $summary['globalsUpserted']],
                ['  └ GLOBAL new (not previously in target)', $globalInserted],
                ['  └ GLOBAL existing (re-upserted in place, no new row)', $globalExisting],
                ['TENANT rows inserted (new identities)', $tenantInserted],
                ['TENANT rows existing (ledger — re-run no-op)', $tenantExisting],
                ['Error-ledger entries this run', $summary['errors']],
                ['Note-ledger entries this run', $summary['notes']],
            ],
        );

        if (! empty($summary['perTable'])) {
            $this->newLine();
            $this->info('Per-table breakdown (Written = new rows, Existing = already present / re-run no-op):');
            $rows = [];
            foreach ($summary['perTable'] as $table => $t) {
                $rows[] = [$table, $t['category'], $t['read'], $t['written'], $t['existing'], $t['errors']];
            }
            $this->table(['Table', 'Category', 'Read', 'Written (new)', 'Existing', 'Errors'], $rows);
        }

        // Verdict line (R14.5 — make idempotency OBSERVABLE). A clean re-run
        // writes no new tenant AND no new global masters; the summary SHOWS it.
        $this->newLine();
        if ($tenantInserted === 0 && $globalInserted === 0) {
            $this->info(sprintf(
                'Idempotent re-run: 0 new tenant masters, 0 new global masters '
                .'(tenantExisting=%d, globalExisting=%d). Re-upserts touch existing GLOBAL rows '
                .'in place (updated_at may move; row COUNTS stable) and skip TENANT rows via the ledger; '
                .'fusion_ledger does not grow.',
                $tenantExisting, $globalExisting,
            ));
        } else {
            $this->info(sprintf(
                'Done. Wrote %d new tenant master(s) and %d new global master(s). GLOBAL upserts are '
                .'idempotent; TENANT inserts recorded (company_id, business_key)->new_id in fusion_ledger. '
                .'A re-run inserts no duplicate TENANT rows and re-upserts GLOBAL rows in place.',
                $tenantInserted, $globalInserted,
            ));
        }
    }
}
