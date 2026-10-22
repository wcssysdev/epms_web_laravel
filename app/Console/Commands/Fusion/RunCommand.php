<?php

namespace App\Console\Commands\Fusion;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `fusion:run` — the estate-db-fusion load+validate ORCHESTRATOR
 * (spec task 11.1, requirements R1.9, R14.5).
 *
 * Spec authority: design.md "Architecture → Execution order (pipeline)" and
 * the Architecture graph; requirements.md R1.9 (the backup-and-truncate
 * command is kept SEPARATE from the idempotent load stages and SHALL NOT run
 * as part of a re-run) and R14.5 (re-running a load stage after a successful
 * run produces the same target state — no duplicate rows, so the whole
 * pipeline is safely resumable).
 *
 * This command is a THIN ORCHESTRATION LAYER. It owns NO business logic: it
 * chains the already-built, individually idempotent, live-verified stage
 * commands in the strict pipeline order and reports a consolidated summary.
 * Every behavioural decision still lives in the per-stage command/service.
 *
 *     seed-tenants → migrate-rbac → copy-masters → copy-transactions → reconcile
 *
 * ## Dry-run / staging validation (R15.1, task 11.2)
 *   `fusion:run --dry-run` runs the ENTIRE pipeline against a DISPOSABLE
 *   staging target (connection `odbc_sqlsrv_staging`, database `EPMS_STAGING`
 *   by default) instead of production EPMS, so an operator can validate the
 *   whole fusion end-to-end — per-stage counts and the final reconcile report —
 *   WITHOUT touching production `EPMS`.
 *
 *   Routing mechanism: every stage service resolves its write connection from
 *   `config('fusion.target_connection')` (default `odbc_sqlsrv`). `--dry-run`
 *   overrides that config value to the staging connection for the duration of
 *   this process; because the stages are invoked via `$this->call()` in the
 *   SAME process, every container-resolved stage picks up the staging target.
 *   The default is UNCHANGED for a normal run, so non-dry-run behaviour is
 *   identical to before.
 *
 *   Safety: `--dry-run` FAILS FAST if the staging connection is unreachable or
 *   has no EPMS schema (it never silently falls through to production), and it
 *   hard-aborts if the staging connection name resolves to production EPMS.
 *   Provisioning the staging DB is a one-time operator step — see
 *   `.kiro/specs/estate-db-fusion/STAGING.md`.
 *
 *       php artisan fusion:run --dry-run --only-db=rsm26 --limit=5
 *
 * ## prepare-target is NOT part of this run (R1.9)
 *   `fusion:run` NEVER truncates and NEVER invokes `fusion:prepare-target`.
 *   A clean-slate reset (full backup + guarded, dependency-ordered truncate)
 *   is a SEPARATE, explicitly-confirmed operator step that must be run FIRST
 *   and on its own:
 *
 *       php artisan fusion:prepare-target --confirm
 *
 *   `fusion:run` only DETECTS whether the tenant scaffold is present
 *   (m_company / m_estate populated). If it is not, seed-tenants runs as the
 *   first stage; it never assumes a truncate happened.
 *
 * ## Resumable idempotency (R14.5)
 *   Because every stage is idempotent, a mid-pipeline failure is recovered by
 *   simply re-running `fusion:run`: completed stages become clean no-ops. Two
 *   options give finer control:
 *     --from=<stage>   start the pipeline at <stage> (skip everything before).
 *     --only=<stage>   run exactly one stage and stop.
 *   Valid stages: seed-tenants | migrate-rbac | copy-masters |
 *                 copy-transactions | reconcile.
 *
 * ## Reconcile handling
 *   reconcile is a READ-ONLY validation stage; by the time it runs the load
 *   has already happened. A reconcile hard-FAIL is therefore reported and
 *   produces a NON-ZERO final exit code, but it never undoes the load. The
 *   --stop-on-reconcile-fail flag is accepted for explicitness (the default
 *   behaviour already treats a reconcile hard-fail as a non-zero exit).
 *
 * ## Forwarded guarded options (so a single-DB end-to-end run is possible)
 *   --only-db=<name>              → migrate-rbac, copy-masters,
 *                                   copy-transactions, reconcile
 *   --limit=<n>                   → migrate-rbac, copy-masters, copy-transactions
 *   --months=<n> / --window=<n>   → copy-transactions, reconcile
 *   --seed-country-admins         → seed-tenants
 *   --super-admin-username=<s>    → seed-tenants
 *   --super-admin-email=<s>       → seed-tenants
 *   --super-admin-password=<s>    → seed-tenants
 *   --country-admin-password=<s>  → seed-tenants
 *
 *   Example guarded end-to-end run against one source DB:
 *       php artisan fusion:run --only-db=rsm26 --limit=20
 *
 * ## Exit code
 *   Returns NON-ZERO when any LOAD stage fails (seed-tenants, migrate-rbac,
 *   copy-masters, copy-transactions) or when reconcile hard-fails. On a load
 *   failure the pipeline STOPS immediately and prints how to resume.
 */
class RunCommand extends Command
{
    protected $signature = 'fusion:run
        {--dry-run : STAGING MODE (R15.1): run the FULL pipeline against the disposable staging target (odbc_sqlsrv_staging / EPMS_STAGING) instead of production EPMS. Fails fast if staging is unreachable; production EPMS is never touched.}
        {--from= : Start the pipeline at this stage (seed-tenants|migrate-rbac|copy-masters|copy-transactions|reconcile); earlier stages are skipped.}
        {--only= : Run exactly ONE stage and stop (seed-tenants|migrate-rbac|copy-masters|copy-transactions|reconcile).}
        {--stop-on-reconcile-fail : Treat a reconcile hard-fail as a non-zero final exit (this is already the default; accepted for explicitness).}
        {--only-db= : Restrict load+validate to a single source db_name (guarded end-to-end run). Forwarded to rbac/masters/transactions/reconcile.}
        {--limit= : Cap source rows read per table (guarded few-rows run). Forwarded to rbac/masters/transactions.}
        {--months= : Override the transaction window N in months. Forwarded to copy-transactions and reconcile.}
        {--window= : Alias of --months.}
        {--seed-country-admins : Forwarded to seed-tenants: provision one country_admin seed user per seeded country.}
        {--super-admin-username= : Forwarded to seed-tenants: super_admin username.}
        {--super-admin-email= : Forwarded to seed-tenants: super_admin email.}
        {--super-admin-password= : Forwarded to seed-tenants: super_admin password (prefer env FUSION_SUPER_ADMIN_PASSWORD).}
        {--country-admin-password= : Forwarded to seed-tenants: country_admin password (prefer env FUSION_COUNTRY_ADMIN_PASSWORD).}';

    protected $description = 'Orchestrate the estate-db-fusion load+validate pipeline (seed-tenants → migrate-rbac → copy-masters → copy-transactions → reconcile). Idempotent and resumable; stops on the first load failure. NEVER truncates — run `fusion:prepare-target --confirm` separately for a clean slate.';

    /** Production target connection name (SQL Server EPMS via ODBC). */
    private const TARGET_CONNECTION = 'odbc_sqlsrv';

    /** Disposable staging target connection name (dry-run, R15.1). */
    private const STAGING_CONNECTION = 'odbc_sqlsrv_staging';

    /** The ordered load+validate pipeline. reconcile is the only read-only stage. */
    private const PIPELINE = [
        'seed-tenants',
        'migrate-rbac',
        'copy-masters',
        'copy-transactions',
        'reconcile',
    ];

    public function handle(): int
    {
        $this->info('fusion:run — estate-db-fusion load+validate orchestrator (task 11.1).');
        $this->warn('NOTE: `fusion:run` NEVER truncates. For a clean slate, run `php artisan fusion:prepare-target --confirm` separately FIRST (R1.9).');
        $this->newLine();

        // ---- Dry-run / staging routing (R15.1) -------------------------------
        // In dry-run mode EVERY stage must hit the disposable staging target and
        // NEVER production EPMS. We do this by overriding the single config value
        // every stage service resolves its target from. The override lives only
        // for this process; the committed default stays 'odbc_sqlsrv'. We FAIL
        // FAST if staging is unreachable so we can never silently fall through to
        // production.
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            if (($setup = $this->enterDryRun()) !== self::SUCCESS) {
                return $setup;
            }
        }

        // ---- Resolve --from / --only into the list of stages to run ----------
        try {
            $stages = $this->resolveStages();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        // ---- R1.9: detect whether the tenant scaffold is already present -----
        // seed-tenants is itself idempotent, so we always run it when in scope;
        // this detection only drives an informational banner (never a truncate
        // assumption).
        $alreadySeeded = $this->tenantScaffoldPresent();
        if (in_array('seed-tenants', $stages, true)) {
            $this->line($alreadySeeded
                ? '  Tenant scaffold DETECTED (m_company / m_estate populated) — seed-tenants will run as an idempotent no-op/upsert.'
                : '  Tenant scaffold NOT detected — seed-tenants will run as the first stage to provision countries/companies/estates.');
            $this->newLine();
        }

        $this->line('  Pipeline: '.implode(' → ', $stages));
        $this->newLine();

        // ---- Run the stages in order -----------------------------------------
        $results = [];
        $pipelineStart = microtime(true);

        foreach ($stages as $stage) {
            $result = $this->runStage($stage);
            $results[] = $result;

            // A LOAD stage failure stops the pipeline immediately (R1.9 guard).
            if ($stage !== 'reconcile' && $result['exit'] !== self::SUCCESS) {
                $this->renderSummary($results, microtime(true) - $pipelineStart, stopped: $stage);
                $this->printResumeHint($stage);

                return self::FAILURE;
            }
        }

        $elapsed = microtime(true) - $pipelineStart;
        $this->renderSummary($results, $elapsed, stopped: null);

        // ---- Final exit code -------------------------------------------------
        // Non-zero if any load stage failed (handled above) OR reconcile
        // hard-failed. reconcile hard-fail does NOT undo the load.
        $reconcile = $this->findResult($results, 'reconcile');
        if ($reconcile !== null && $reconcile['exit'] !== self::SUCCESS) {
            $this->newLine();
            $this->error('OVERALL: load completed but RECONCILE HARD-FAILED — see the reconcile verdict above. The load is NOT undone; investigate and re-run `fusion:reconcile`.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Enter DRY-RUN / staging mode (R15.1).
     *
     * Reroutes the fusion target to the disposable staging connection, verifies
     * that connection is reachable (fail fast — never silently fall through to
     * production EPMS), prints a prominent banner, and guarantees the production
     * EPMS connection is NOT the resolved target. Returns SUCCESS when staging
     * is ready, or a non-zero exit when staging cannot be reached.
     */
    private function enterDryRun(): int
    {
        $staging = (string) config('fusion.staging_connection', self::STAGING_CONNECTION);

        // Route EVERY stage service (and this orchestrator's own probes) to the
        // staging connection. Services resolve config('fusion.target_connection')
        // at construction, and RunCommand invokes them via $this->call() in THIS
        // process, so the override is picked up by every container-resolved stage.
        config(['fusion.target_connection' => $staging]);

        // Hard guard: the resolved target must NOT be production EPMS.
        if ($staging === self::TARGET_CONNECTION) {
            $this->error('DRY-RUN ABORTED: the staging connection resolves to the production target "'
                .self::TARGET_CONNECTION.'". Set FUSION_STAGING_CONNECTION to a disposable connection (e.g. odbc_sqlsrv_staging).');

            return self::FAILURE;
        }

        // Describe the staging DB (name only — never credentials).
        $db = (string) config('database.connections.'.$staging.'.database', '(unknown)');

        $this->newLine();
        $this->warn('╔══════════════════════════════════════════════════════════════════════╗');
        $this->warn('║  DRY RUN — STAGING MODE (R15.1)                                        ║');
        $this->warn('║  The FULL pipeline runs against the DISPOSABLE staging target.         ║');
        $this->warn('║  Production EPMS (connection "odbc_sqlsrv") is NOT touched.             ║');
        $this->warn('╚══════════════════════════════════════════════════════════════════════╝');
        $this->line('  Target connection : '.$staging);
        $this->line('  Target database   : '.$db);
        $this->line('  Production EPMS    : odbc_sqlsrv (UNTOUCHED)');
        $this->newLine();

        // Fail fast if staging is unreachable or has no schema, rather than
        // silently writing nothing (or, worse, falling through to prod).
        try {
            $conn = DB::connection($staging);
            $conn->getPdo(); // force a real connection attempt

            // Sanity-probe a core target table so an empty / schema-less staging
            // DB fails here with actionable guidance instead of mid-pipeline.
            $conn->table('m_company')->limit(1)->count();
        } catch (\Throwable $e) {
            $this->error('DRY-RUN ABORTED: staging target "'.$staging.'" (database "'.$db.'") is not reachable or has no EPMS schema.');
            $this->line('  Reason: '.$e->getMessage());
            $this->newLine();
            $this->warn('Provision the staging DB first (operator step). See:');
            $this->line('    .kiro/specs/estate-db-fusion/STAGING.md');
            $this->line('  In short (SQL Server):');
            $this->line('    1) CREATE DATABASE EPMS_STAGING;');
            $this->line('    2) Copy the EPMS schema into EPMS_STAGING (schema-only generate-scripts, or restore a copy).');
            $this->line('    3) Optionally seed dummy markers so prepare-target guards pass.');
            $this->line('  Then set FUSION_STAGING_DATABASE / FUSION_STAGING_* in .env if the defaults do not match.');

            return self::FAILURE;
        }

        $this->info('  Staging target reachable — proceeding with the dry run. EPMS will not be written.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * The connection name the pipeline will actually hit, honoring a dry-run
     * override. Defaults to production EPMS when not in dry-run mode.
     */
    private function effectiveTargetConnection(): string
    {
        return (string) config('fusion.target_connection', self::TARGET_CONNECTION);
    }

    /**
     * Resolve the stages to run from --from / --only.
     *
     * @return list<string>
     *
     * @throws \InvalidArgumentException on an unknown stage name or if both
     *         --from and --only are given.
     */
    private function resolveStages(): array
    {
        $from = $this->normalizedOption('from');
        $only = $this->normalizedOption('only');

        if ($from !== null && $only !== null) {
            throw new \InvalidArgumentException('Pass EITHER --from or --only, not both.');
        }

        if ($only !== null) {
            $this->assertValidStage($only, '--only');

            return [$only];
        }

        if ($from !== null) {
            $this->assertValidStage($from, '--from');
            $index = array_search($from, self::PIPELINE, true);

            return array_slice(self::PIPELINE, (int) $index);
        }

        return self::PIPELINE;
    }

    private function assertValidStage(string $stage, string $flag): void
    {
        if (! in_array($stage, self::PIPELINE, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown stage "%s" for %s. Valid stages: %s.',
                $stage,
                $flag,
                implode(', ', self::PIPELINE),
            ));
        }
    }

    /**
     * Invoke a single stage command with its forwarded options, print a
     * start/finish banner with the elapsed time and the stage summary line,
     * and capture the exit code + output.
     *
     * @return array{stage:string, exit:int, elapsed:float, summary:string}
     */
    private function runStage(string $stage): array
    {
        $commandName = 'fusion:'.$stage;
        $arguments = $this->argumentsFor($stage);

        $this->newLine();
        $this->info('──────────────────────────────────────────────────────────────');
        $this->info(sprintf('▶ STAGE START: %s %s', $commandName, $this->describeArguments($arguments)));
        $this->info('──────────────────────────────────────────────────────────────');

        $start = microtime(true);
        try {
            $exit = $this->call($commandName, $arguments);
        } catch (\Throwable $e) {
            $elapsed = microtime(true) - $start;
            $this->newLine();
            $this->error(sprintf('✖ STAGE THREW: %s — %s', $commandName, $e->getMessage()));

            return [
                'stage'   => $stage,
                'exit'    => self::FAILURE,
                'elapsed' => $elapsed,
                'summary' => 'threw: '.$e->getMessage(),
            ];
        }
        $elapsed = microtime(true) - $start;

        $ok = $exit === self::SUCCESS;
        $line = sprintf(
            '%s STAGE %s: %s (exit %d, %.2fs)',
            $ok ? '✔' : '✖',
            $ok ? 'FINISH' : 'FAILED',
            $commandName,
            $exit,
            $elapsed,
        );
        $this->newLine();
        $ok ? $this->info($line) : $this->error($line);

        return [
            'stage'   => $stage,
            'exit'    => $exit,
            'elapsed' => $elapsed,
            'summary' => $ok ? 'ok' : 'exit '.$exit,
        ];
    }

    /**
     * Build the forwarded option array for a given stage. Only options the
     * target command actually declares are forwarded (unknown options would
     * make the inner command abort).
     *
     * @return array<string,mixed>
     */
    private function argumentsFor(string $stage): array
    {
        $onlyDb = $this->normalizedOption('only-db');
        $limit  = $this->normalizedOption('limit');
        $months = $this->normalizedOption('months');
        $window = $this->normalizedOption('window');

        return match ($stage) {
            'seed-tenants' => $this->filterNull([
                '--seed-country-admins'    => (bool) $this->option('seed-country-admins') ?: null,
                '--super-admin-username'   => $this->normalizedOption('super-admin-username'),
                '--super-admin-email'      => $this->normalizedOption('super-admin-email'),
                '--super-admin-password'   => $this->normalizedOption('super-admin-password'),
                '--country-admin-password' => $this->normalizedOption('country-admin-password'),
            ]),
            'migrate-rbac' => $this->filterNull([
                '--only-db' => $onlyDb,
                '--limit'   => $limit,
            ]),
            'copy-masters' => $this->filterNull([
                '--only-db' => $onlyDb,
                '--limit'   => $limit,
            ]),
            'copy-transactions' => $this->filterNull([
                '--only-db' => $onlyDb,
                '--limit'   => $limit,
                '--months'  => $months,
                '--window'  => $window,
            ]),
            'reconcile' => $this->filterNull([
                '--only-db' => $onlyDb,
                '--months'  => $months,
                '--window'  => $window,
            ]),
            default => [],
        };
    }

    /**
     * Drop null values but keep boolean true flags (passed as `--flag => true`).
     *
     * @param  array<string,mixed>  $arguments
     * @return array<string,mixed>
     */
    private function filterNull(array $arguments): array
    {
        return array_filter($arguments, static fn ($value) => $value !== null);
    }

    /**
     * Detect whether the tenant scaffold is already present in the target.
     * Used only for an informational banner (R1.9): seed-tenants is idempotent
     * and runs regardless when in scope. Never assumes a truncate happened.
     */
    private function tenantScaffoldPresent(): bool
    {
        try {
            $target = DB::connection($this->effectiveTargetConnection());
            $companies = (int) $target->table('m_company')->count();
            $estates = (int) $target->table('m_estate')->count();

            return $companies > 0 && $estates > 0;
        } catch (\Throwable $e) {
            // Detection is best-effort; a failure here must not block the run.
            $this->warn('  Could not probe tenant scaffold ('.$e->getMessage().') — proceeding; seed-tenants is idempotent.');

            return false;
        }
    }

    /**
     * Print the consolidated per-stage summary with statuses, elapsed time and
     * the reconcile verdict.
     *
     * @param  list<array{stage:string, exit:int, elapsed:float, summary:string}>  $results
     */
    private function renderSummary(array $results, float $totalElapsed, ?string $stopped): void
    {
        $this->newLine();
        $this->info('================ fusion:run consolidated summary ================');

        $rows = [];
        foreach (self::PIPELINE as $stage) {
            $result = $this->findResult($results, $stage);
            if ($result === null) {
                $rows[] = [$stage, 'SKIPPED', '—', '—'];

                continue;
            }
            $status = $result['exit'] === self::SUCCESS ? 'OK' : 'FAIL';
            $rows[] = [
                $stage,
                $status,
                sprintf('%.2fs', $result['elapsed']),
                $this->truncate($result['summary'], 48),
            ];
        }
        $this->table(['Stage', 'Status', 'Elapsed', 'Detail'], $rows);

        $this->line(sprintf('  Total elapsed: %.2fs', $totalElapsed));
        if ($stopped !== null) {
            $this->error(sprintf('  Pipeline STOPPED at load stage: fusion:%s (non-zero exit).', $stopped));
        }

        // Reconcile verdict (the validation outcome), if reconcile ran.
        $reconcile = $this->findResult($results, 'reconcile');
        if ($reconcile !== null) {
            $reconcile['exit'] === self::SUCCESS
                ? $this->info('  Reconcile verdict: PASS (or PASS WITH WARNINGS) — see the reconcile report above.')
                : $this->error('  Reconcile verdict: FAIL — a hard check failed (see the reconcile report above). The load is NOT undone.');
        } elseif ($stopped === null) {
            $this->warn('  Reconcile did not run in this invocation (not in the selected stage set).');
        }

        $this->info('=================================================================');
    }

    private function printResumeHint(string $failedStage): void
    {
        $this->newLine();
        $this->warn('To resume after fixing the cause, re-run from the failed stage (every stage is idempotent, so completed stages are no-ops):');
        $this->line(sprintf('    php artisan fusion:run --from=%s%s', $failedStage, $this->echoGuardedOptions()));
        $this->line('Or simply re-run `php artisan fusion:run` to replay the whole idempotent pipeline.');
    }

    /** Reflect the guarded options the operator passed, for the resume hint. */
    private function echoGuardedOptions(): string
    {
        $parts = '';
        if (($v = $this->normalizedOption('only-db')) !== null) {
            $parts .= ' --only-db='.$v;
        }
        if (($v = $this->normalizedOption('limit')) !== null) {
            $parts .= ' --limit='.$v;
        }
        if (($v = $this->normalizedOption('months')) !== null) {
            $parts .= ' --months='.$v;
        }

        return $parts;
    }

    /**
     * @param  list<array{stage:string, exit:int, elapsed:float, summary:string}>  $results
     * @return array{stage:string, exit:int, elapsed:float, summary:string}|null
     */
    private function findResult(array $results, string $stage): ?array
    {
        foreach ($results as $result) {
            if ($result['stage'] === $stage) {
                return $result;
            }
        }

        return null;
    }

    /** Render forwarded arguments for the start banner. */
    private function describeArguments(array $arguments): string
    {
        if ($arguments === []) {
            return '(no forwarded options)';
        }
        $parts = [];
        foreach ($arguments as $key => $value) {
            $parts[] = $value === true ? $key : $key.'='.(str_contains((string) $key, 'password') ? '******' : $value);
        }

        return implode(' ', $parts);
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

    /** Truncate a long string for a table cell. */
    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max - 1).'…';
    }
}
