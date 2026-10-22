<?php

namespace App\Console\Commands\Fusion;

use App\Services\Fusion\SafetyManager;
use App\Services\Fusion\SafetyReport;
use Illuminate\Console\Command;

/**
 * `fusion:prepare-target` — the pre-load safety stage of estate-db-fusion.
 *
 * Spec authority: design.md "Safety flow: backup + ordered truncate" + LLD-10;
 * requirements.md Requirement 1 (R1.1-R1.9). This command is a THIN WRAPPER: it
 * wires options and console output and delegates every behavioural decision to
 * {@see SafetyManager::prepareTarget()} so the logic is unit/behaviour testable.
 *
 * ## It does three guarded things, in strict order, then truncates:
 *   1. requires --confirm (no flag => abort, NO truncate)                  R1.1
 *   2. runs the non-production guard (dummy markers + operator gate)       R1.2/R1.3
 *   3. takes a full timestamped EPMS backup and confirms the .bak          R1.4/R1.5
 *   4. truncates dummy data in dependency order, reseeds identity,
 *      retains m_country, and prints per-table cleaned counts              R1.6/R1.7/R1.8
 *
 * ## SEPARATION (R1.9) — DO NOT CHAIN INTO fusion:run
 * This command is destructive and stand-alone. It is NOT one of the idempotent,
 * re-runnable load stages and MUST NOT be invoked as part of the `fusion:run`
 * orchestrator (task 11). An operator runs it ONCE, explicitly, before loading.
 *
 * ## Backup path constraint (R1.4)
 * `BACKUP DATABASE` runs on the SQL Server HOST. `--backup-dir` (or
 * FUSION_BACKUP_DIR) must be a directory writable by the SQL Server service
 * account, not merely by the machine running artisan.
 *
 * Exit code: 0 only on a completed run; non-zero on any abort (so CI/operators
 * can detect that nothing was truncated).
 */
class PrepareTargetCommand extends Command
{
    /**
     * --confirm       : mandatory destructive acknowledgement (R1.1).
     * --i-understand  : operator gate for non-local environments (R1.2); never
     *                   overrides the hard production block.
     * --backup-dir=   : server-writable directory for the .bak (R1.4). Falls
     *                   back to FUSION_BACKUP_DIR, then a documented default.
     */
    protected $signature = 'fusion:prepare-target
        {--confirm : REQUIRED. Acknowledge the destructive truncate. Without it the command refuses to truncate.}
        {--i-understand : Operator gate allowing a non-"local" (but non-production) target once dummy markers are confirmed.}
        {--backup-dir= : Directory on the SQL Server host for the timestamped .bak file (must be writable by the SQL Server service).}';

    protected $description = 'SAFETY: back up EPMS and truncate the dummy data in dependency order before a fusion load (requires --confirm; never part of fusion:run).';

    /** Default server-side backup directory when no option/env is supplied. */
    private const DEFAULT_BACKUP_DIR = 'C:\\SQLBackups\\fusion';

    public function handle(SafetyManager $safety): int
    {
        $this->warn('fusion:prepare-target is a DESTRUCTIVE, stand-alone safety step — it is NOT part of fusion:run.');

        $confirmed = (bool) $this->option('confirm');
        $iUnderstand = (bool) $this->option('i-understand');
        $backupDir = (string) ($this->option('backup-dir')
            ?: env('FUSION_BACKUP_DIR', self::DEFAULT_BACKUP_DIR));

        $this->line('Backup directory (on SQL Server host): '.$backupDir);

        $report = $safety->prepareTarget(
            confirmed: $confirmed,
            iUnderstand: $iUnderstand,
            backupDir: $backupDir,
            progress: fn (string $level, string $message) => $this->emit($level, $message),
        );

        return $this->renderReport($report);
    }

    /** Map a SafetyManager progress level to the matching console writer. */
    private function emit(string $level, string $message): void
    {
        match ($level) {
            'error' => $this->error($message),
            'warn' => $this->warn($message),
            default => $this->line($message),
        };
    }

    /** Print the final report table and return the process exit code. */
    private function renderReport(SafetyReport $report): int
    {
        if ($report->wasAborted()) {
            $this->newLine();
            $this->error('ABORTED ('.$report->reasonCode.'): '.$report->reason);
            $this->line('No truncate was performed. The target is unchanged.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Cleaned row counts per table (m_country retained):');
        $this->table(
            ['Table', 'Before', 'After', 'Cleaned', 'Method'],
            array_map(static fn (array $r) => [
                $r['table'], $r['before'], $r['after'], $r['cleaned'], $r['method'],
            ], $report->cleaned),
        );

        $this->info(sprintf(
            'Done. %d table(s) truncated, %d row(s) cleaned. Backup: %s',
            count($report->cleaned),
            $report->totalCleaned(),
            $report->backupFile,
        ));

        return self::SUCCESS;
    }
}
