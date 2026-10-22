<?php

namespace App\Console\Commands\Fusion;

use App\Services\Fusion\Reconciler;
use Illuminate\Console\Command;

/**
 * `fusion:reconcile` — the post-load validation stage of estate-db-fusion
 * (spec task 10, requirements R15).
 *
 * Spec authority: design.md LLD-11 (validation / reconciliation plan), the
 * Reconciler node in the Architecture graph; requirements.md R15.1 (row-count
 * reconciliation after filters + logged skips), R15.2 (tenant isolation — no
 * NULL company_id, every company_id in m_company), R15.3 (no business key spans
 * two company_id values unless GLOBAL).
 *
 * This command is a THIN WRAPPER: it wires console output + run-constraint
 * options and delegates every behavioural decision to
 * {@see Reconciler::reconcile()} so the logic is unit/behaviour testable
 * (mirrors {@see CopyMastersCommand} / {@see CopyTransactionsCommand}).
 *
 * ## What this stage does TODAY (task 10.1 — row-count + tenant-isolation)
 *   1. ROW-COUNT RECONCILIATION (R15.1): per source DB, per in-scope table,
 *      compares EXPECTED (post-filter source rows MINUS logged ledger skips —
 *      the transaction window is RECOMPUTED with the SAME TransactionCopier
 *      window) vs LOADED (live target rows attributable to the source/company).
 *      A shortfall FULLY explained by logged skips is a WARNING; an unexplained
 *      shortfall is a hard FAIL (silent loss). The deferred headers (t_overtime,
 *      tr_gi_header, tr_gr_header) and their children are reported
 *      EXPECTED-DEFERRED, not losses. A target OVER-count (pre-existing dummy /
 *      foreign-company data) is flagged, never failed (read-only stage).
 *   2. TENANT ISOLATION (R15.2): asserts NO tenant table (any live target table
 *      with a company_id column) has a NULL company_id and every distinct
 *      company_id resolves to an m_company row — HARD FAIL on a violation.
 *   3. CROSS-TENANT LEAK (R15.3): asserts no business key spans >1 company_id in
 *      a tenant table (GLOBAL masters excluded — legitimately shared) — HARD
 *      FAIL on a violation.
 *
 * ## Deferred to later tasks (deliberate seams — reported NOT_RUN today)
 *   - 10.2: RBAC integrity (R15.4) + exclusion proof (R15.6).
 *   - 10.3: tiered-login spot-check (R15.5 / R10).
 *
 * ## Exit code
 *   Returns a NON-ZERO exit code when any HARD check fails (NULL/orphan
 *   company_id, cross-tenant leak, or an UNEXPLAINED row-count shortfall). A
 *   WARNING (ledger-explained mismatch, over-count, deferred header) does NOT
 *   fail the exit code — it is the Reconciler doing its job.
 *
 * ## READ-ONLY
 *   This command NEVER truncates and NEVER writes to a load table. With
 *   --record-to-ledger it may record a one-line verdict note to the ledger (the
 *   only write it ever performs, and only to the ledger).
 *
 * ## Guarded validation options
 *   --only-db=<name>       restrict the report to a single source db_name.
 *   --months=<n>           override the window N so EXPECTED matches the copy.
 *   --window=<n>           alias of --months.
 *   --record-to-ledger     record a one-line verdict note to the ledger.
 */
class ReconcileCommand extends Command
{
    protected $signature = 'fusion:reconcile
        {--only-db= : Restrict the report to a single source db_name (guarded validation run).}
        {--months= : Override the transaction window N in months (default config fusion.window_months=2) so EXPECTED matches the copy.}
        {--window= : Alias of --months.}
        {--record-to-ledger : Record a one-line reconciliation verdict note to the ledger (the only write; off by default).}';

    protected $description = 'Reconcile the loaded EPMS target against the CI3 sources: per-table expected-vs-loaded row counts (ledger-explained), tenant isolation (no NULL/orphan company_id), and cross-tenant business-key leak. Read-only; non-zero exit on a hard failure.';

    public function handle(Reconciler $reconciler): int
    {
        $this->info('fusion:reconcile — row-count, tenant-isolation, RBAC-integrity & exclusion checks. READ-ONLY. No truncate.');
        $this->warn('Prerequisite: the load stages (seed-tenants, migrate-rbac, copy-masters, copy-transactions) have run.');
        $this->newLine();

        $options = [
            'only_db'          => $this->normalizedOption('only-db'),
            'months'           => $this->intOption('months') ?? $this->intOption('window'),
            'record_to_ledger' => (bool) $this->option('record-to-ledger'),
        ];

        if ($options['only_db'] !== null) {
            $this->line('Restricting report to source DB: '.$options['only_db']);
        }
        if ($options['months'] !== null) {
            $this->line('Window override: N = '.$options['months'].' month(s).');
        }

        try {
            $report = $reconciler->reconcile(
                progress: fn (string $level, string $message) => $this->emit($level, $message),
                options: $options,
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Reconciliation failed to run: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($report);

        // Non-zero exit code when any HARD check failed (R15 acceptance).
        return $report['overallVerdict'] === Reconciler::FAIL ? self::FAILURE : self::SUCCESS;
    }

    /** Map a reconciler progress level to the matching console writer. */
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
     * Print the full reconciliation report: a per-check PASS/FAIL summary, the
     * row-count table, the tenant-isolation and cross-tenant-leak results, the
     * ledger cross-reference, and the overall verdict.
     *
     * @param  array<string,mixed>  $report
     */
    private function renderReport(array $report): void
    {
        $checks = $report['checks'];

        // ---- Row counts (R15.1) ---------------------------------------------
        $rc = $checks['row_counts'];
        $this->newLine();
        $this->info('Check R15.1 — Row-count reconciliation: '.$this->badge($rc['verdict']));
        $this->line('  '.$rc['summary']);
        if (! empty($rc['rows'])) {
            $tableRows = [];
            foreach ($rc['rows'] as $r) {
                $tableRows[] = [
                    $r['db'],
                    $r['table'],
                    $r['category'],
                    $r['expected'],
                    $r['loaded'],
                    sprintf('%+d', $r['delta']),
                    $r['verdict'],
                    $this->truncate($r['cause'], 60),
                ];
            }
            $this->table(
                ['DB', 'Table', 'Category', 'Expected', 'Loaded', 'Delta', 'Verdict', 'Cause'],
                $tableRows,
            );
        }

        // ---- Tenant isolation (R15.2) ---------------------------------------
        $ti = $checks['tenant_isolation'];
        $this->newLine();
        $this->info('Check R15.2 — Tenant isolation: '.$this->badge($ti['verdict']));
        $this->line('  '.$ti['summary']);
        if (! empty($ti['violations'])) {
            $vrows = [];
            foreach ($ti['violations'] as $v) {
                $vrows[] = [$v['table'], $v['nullCount'], implode(',', $v['orphanIds'])];
            }
            $this->table(['Table', 'NULL company_id', 'Orphan company_id(s)'], $vrows);
        }

        // ---- Cross-tenant leak (R15.3) --------------------------------------
        $ct = $checks['cross_tenant_leak'];
        $this->newLine();
        $this->info('Check R15.3 — Cross-tenant business-key leak: '.$this->badge($ct['verdict']));
        $this->line('  '.$ct['summary']);
        if (! empty($ct['violations'])) {
            $vrows = [];
            foreach ($ct['violations'] as $v) {
                $vrows[] = [$v['table'], $v['businessKey'], $v['spanningKeys']];
            }
            $this->table(['Table', 'Business Key', 'Keys spanning >1 company'], $vrows);
        }

        // ---- RBAC integrity (R15.4) -----------------------------------------
        $ri = $checks['rbac_integrity'];
        $this->newLine();
        $this->info('Check R15.4 — RBAC integrity: '.$this->badge($ri['verdict']));
        $this->line('  '.$ri['summary']);
        if (! empty($ri['rows'])) {
            $rrows = [];
            foreach ($ri['rows'] as $r) {
                $rrows[] = [$r['rule'] ?? '', $this->truncate((string) ($r['metric'] ?? ''), 80), $this->badge($r['verdict'] ?? '')];
            }
            $this->table(['Rule', 'Metric', 'Verdict'], $rrows);
        }
        if (! empty($ri['violations'])) {
            $this->warn('  RBAC violations:');
            foreach ($ri['violations'] as $v) {
                $this->line('    - '.json_encode($v));
            }
        }

        // ---- Exclusion proof (R15.6) ----------------------------------------
        $ex = $checks['exclusions'];
        $this->newLine();
        $this->info('Check R15.6 — Exclusion proof (res_data / audit_trail): '.$this->badge($ex['verdict']));
        $this->line('  '.$ex['summary']);
        if (! empty($ex['rows'])) {
            $erows = [];
            foreach ($ex['rows'] as $r) {
                $erows[] = [$this->truncate((string) ($r['check'] ?? ''), 70), $this->truncate((string) ($r['metric'] ?? ''), 50), $this->badge($r['verdict'] ?? '')];
            }
            $this->table(['Check', 'Metric', 'Verdict'], $erows);
        }
        if (! empty($ex['violations'])) {
            $this->error('  Exclusion violations:');
            foreach ($ex['violations'] as $v) {
                $this->line('    - '.json_encode($v));
            }
        }

        // ---- Tiered-login spot-check (R15.5 / R10) --------------------------
        $tl = $checks['tiered_login'];
        $this->newLine();
        $this->info('Check R15.5 — Tiered-login spot-check (HasCompanyScope visibility per tier): '.$this->badge($tl['verdict']));
        $this->line('  '.$tl['summary']);
        if (! empty($tl['rows'])) {
            $trows = [];
            foreach ($tl['rows'] as $r) {
                $trows[] = [
                    $r['tier'] ?? '',
                    (string) ($r['user_id'] ?? ''),
                    $this->truncate((string) ($r['expectedVisible'] ?? ''), 48),
                    $this->truncate((string) ($r['actualVisible'] ?? ''), 36),
                    $this->badge($r['verdict'] ?? ''),
                    $this->truncate((string) ($r['note'] ?? ''), 70),
                ];
            }
            $this->table(['Tier', 'User', 'Expected (tier => set)', 'Actual visible', 'Verdict', 'Note'], $trows);
        }
        if (! empty($tl['violations'])) {
            $this->error('  Tiered-visibility breaches:');
            foreach ($tl['violations'] as $v) {
                $this->line('    - '.json_encode($v));
            }
        }

        // ---- Ledger cross-reference -----------------------------------------
        $led = $report['ledger'];
        $this->newLine();
        $this->info('Ledger cross-reference (explains row-count mismatches):');
        $this->table(
            ['Ledger entry', 'Count'],
            [
                ['error (skips: NOT-NULL, unmapped role, no-email, coercion, ...)', $led['errors']],
                ['note (deferred headers, empty attendance window, ambiguous key)', $led['notes']],
                ['txn_header_loaded (loaded VARCHAR header ids)', $led['headerLoaded']],
                ['txn_child_loaded (loaded child natural keys)', $led['childLoaded']],
                ['attendance_natural_key (loaded attendance rows)', $led['attendanceNk']],
            ],
        );

        // ---- Overall verdict ------------------------------------------------
        $this->newLine();
        $this->info('=== Reconciliation verdict ===');
        $this->line(sprintf('  Source DBs in scope : %d', $report['databases']));
        $this->line(sprintf('  Hard failures       : %d', $report['hardFailures']));
        $this->line(sprintf('  Warnings            : %d', $report['warnings']));
        $this->newLine();

        match ($report['overallVerdict']) {
            Reconciler::PASS => $this->info('OVERALL: PASS — counts reconcile, tenant isolation clean, no cross-tenant leak.'),
            Reconciler::WARN => $this->warn('OVERALL: PASS WITH WARNINGS — all mismatches are explained by logged skips / deferrals / over-counts (no silent loss, no isolation breach). This is the Reconciler doing its job.'),
            default          => $this->error('OVERALL: FAIL — at least one HARD check failed (NULL/orphan company_id, cross-tenant leak, or an UNEXPLAINED row-count shortfall). See the violations above.'),
        };
    }

    /** A short colored-ish verdict badge for the console. */
    private function badge(string $verdict): string
    {
        return match ($verdict) {
            Reconciler::PASS    => 'PASS',
            Reconciler::FAIL    => 'FAIL',
            Reconciler::WARN    => 'WARN',
            Reconciler::NOT_RUN => 'NOT RUN',
            default             => $verdict,
        };
    }

    /** Truncate a long cause string for the table cell. */
    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max - 1).'…';
    }
}
