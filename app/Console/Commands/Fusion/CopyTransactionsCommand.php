<?php

namespace App\Console\Commands\Fusion;

use App\Services\Fusion\TransactionCopier;
use Illuminate\Console\Command;

/**
 * `fusion:copy-transactions` — the transaction copy stage of estate-db-fusion
 * (spec task 8, requirements R12/R13/R14).
 *
 * Spec authority: design.md LLD-3 (table categories), LLD-4 (per-table date
 * registry + window), LLD-8 (ID/collision), LLD-9 (idempotency), sequence
 * diagram (d); requirements.md R12 (windowed copy + header-child integrity),
 * R13 (cross-engine load), R14 (idempotent re-run).
 *
 * This command is a THIN WRAPPER: it wires console output + run-constraint
 * options and delegates every behavioural decision to
 * {@see TransactionCopier::copy()} so the logic is unit/behaviour testable
 * (mirrors {@see CopyMastersCommand}).
 *
 * ## What this stage does TODAY (task 8.1 — window + VARCHAR-id header copy)
 *   - iterates the 12 source DBs via SourceRegistry;
 *   - per DB: computes AnchorDate = max(date) across the present header tables
 *     (each header uses its own DateColumnRegistry date column, validated against
 *     the LIVE source) and windowStart = AnchorDate − N months (R12.1/R12.2);
 *   - copies the carry-verbatim VARCHAR-id headers (t_oph, t_cp, t_fdn,
 *     t_workdone, t_vra, t_coconut_oph, t_coconut_fdn): filter by the header's own
 *     date column within the window, carry the VARCHAR id verbatim, inject
 *     company_id, remap audit (R12.3/R12.6); a header with no resolvable date
 *     column is FULL-copied + warned (R12.9);
 *   - records each loaded header id to the fusion_ledger so later tasks (8.2
 *     children, 8.4 idempotency) can build on the loaded header set;
 *   - a header already present by id is NOT re-inserted (idempotency guard,
 *     R14.1/R14.4 — formally hardened in 8.4);
 *   - per-row coercion errors are recorded to the error ledger and the batch
 *     CONTINUES (R13.5); whole-table/DB failures are recorded and the stage
 *     moves on.
 *
 * ## What this stage ALSO does now (task 8.2 — bigint-id children)
 *   - after each parent header is copied, pulls that header's in-scope child
 *     tables by the parent's VARCHAR business key restricted to the LOADED header
 *     set (never by date, R12.4/R12.5 — no orphans), drops the source bigint id
 *     for a fresh identity (R12.7), preserves the VARCHAR business FK verbatim,
 *     injects company_id, remaps audit, and dedupes by the child natural key so a
 *     re-run inserts 0 duplicates (R14.4). Children of the deferred headers are
 *     recorded as a note and skipped.
 *
 * ## What this stage ALSO does now (task 8.3 — t_attendance special case)
 *   - after the headers/children of each source DB, copies t_attendance by its
 *     OWN date column (attendance_date) within the SAME per-DB window, drops the
 *     source bigint id for a fresh identity, injects company_id, remaps audit,
 *     and records the natural key (company_id, employee_code, attendance_date) →
 *     new target id in the fusion_ledger so later referrers resolve the new
 *     identity (R12.8). Dedupes by the natural key so a re-run inserts 0
 *     duplicates (R14.4). When a DB's per-DB window (driven by the header anchor)
 *     excludes all attendance rows, that is recorded as a note (the design's
 *     per-DB window is followed literally — no separate attendance anchor).
 *
 * ## Deferred to later tasks (deliberate seams)
 *   - 8.4 idempotency hardening. The headers t_overtime / tr_gi_header /
 *     tr_gr_header are also
 *     deferred by 8.1 (their id is not a carry-verbatim globally-unique VARCHAR —
 *     recorded as a note, never guessed); they still contribute to AnchorDate.
 *
 * ## Prerequisites
 *   Run `fusion:seed-tenants` first — this stage resolves company ids by CODE
 *   against the seeded tenant tables. (copy-masters is normally run before this
 *   too, but a header smoke needs only seeded tenants.) A source DB whose estate
 *   is not seeded is recorded to the error ledger and skipped.
 *
 * ## Guarded validation options
 *   --only-db=<name>  restrict the run to a single source db_name.
 *   --limit=<n>       cap the source rows read PER header table (few-rows smoke).
 *   --months=<n>      override the window N (months); same as --window.
 *   --window=<n>      alias of --months.
 *
 * This command NEVER truncates and NEVER uses pg_dump/pg_restore.
 */
class CopyTransactionsCommand extends Command
{
    protected $signature = 'fusion:copy-transactions
        {--only-db= : Restrict the run to a single source db_name (guarded validation run).}
        {--limit= : Cap the number of source rows read per header table (guarded few-rows run).}
        {--months= : Override the transaction window N in months (default config fusion.window_months=2).}
        {--window= : Alias of --months.}';

    protected $description = 'Copy CI3 transaction headers into multi-tenant EPMS within a per-DB window (AnchorDate − N months). VARCHAR-id headers carry their id verbatim and are stamped with company_id; a header with no date column is full-copied + warned. Never truncates; safe to re-run (headers present by id are skipped).';

    public function handle(TransactionCopier $copier): int
    {
        $this->info('fusion:copy-transactions — windowed transaction header copy (task 8.1). No truncate, no pg_dump.');
        $this->warn('Prerequisite: run `php artisan fusion:seed-tenants` first (tenant ids are resolved by code).');
        $this->newLine();

        $options = [
            'only_db' => $this->normalizedOption('only-db'),
            'limit'   => $this->intOption('limit'),
            'months'  => $this->intOption('months') ?? $this->intOption('window'),
        ];

        if ($options['only_db'] !== null) {
            $this->line('Restricting run to source DB: '.$options['only_db']);
        }
        if ($options['limit'] !== null) {
            $this->line('Limiting to '.$options['limit'].' source row(s) per header table.');
        }
        if ($options['months'] !== null) {
            $this->line('Window override: N = '.$options['months'].' month(s).');
        }

        try {
            $summary = $copier->copy(
                progress: fn (string $level, string $message) => $this->emit($level, $message),
                options: $options,
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Transaction copy failed: '.$e->getMessage());

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
     * Print the final copy summary plus a per-DB window and per-table breakdown.
     *
     * @param  array{databases:int, headersProcessed:int, headersDeferred:int, headersFullCopied:int, inserted:int, existing:int, errors:int, notes:int, childrenRead:int, childrenInserted:int, childrenExisting:int, childrenErrors:int, attendanceRead:int, attendanceInserted:int, attendanceExisting:int, attendanceErrors:int, windows:array<string,array{anchor:?string,windowStart:?string,months:int}>, perTable:array<string,array{db:string,dateColumn:?string,read:int,written:int,existing:int,errors:int,fullCopy:bool}>, perChildTable:array<string,array{db:string,parent:string,parentFk:string,read:int,inserted:int,existing:int,errors:int}>}  $summary
     */
    private function renderSummary(array $summary): void
    {
        $this->newLine();
        $this->info('Transaction copy summary:');
        $this->table(
            ['Item', 'Count'],
            [
                ['Source DBs processed', $summary['databases']],
                ['Headers processed (VARCHAR-id carry)', $summary['headersProcessed']],
                ['Headers deferred (id strategy unresolved)', $summary['headersDeferred']],
                ['Headers full-copied (no date column, R12.9)', $summary['headersFullCopied']],
                ['Header rows inserted (new)', $summary['inserted']],
                ['Header rows existing (already present by id — re-run no-op)', $summary['existing']],
                ['Child rows read (by loaded header set)', $summary['childrenRead'] ?? 0],
                ['Child rows inserted (new, re-identity)', $summary['childrenInserted'] ?? 0],
                ['Child rows existing (natural-key — re-run no-op)', $summary['childrenExisting'] ?? 0],
                ['Child row errors (coerce/insert)', $summary['childrenErrors'] ?? 0],
                ['Attendance rows read (own date window)', $summary['attendanceRead'] ?? 0],
                ['Attendance rows inserted (new, re-identity + NK ledger)', $summary['attendanceInserted'] ?? 0],
                ['Attendance rows existing (natural-key — re-run no-op)', $summary['attendanceExisting'] ?? 0],
                ['Attendance row errors (coerce/insert)', $summary['attendanceErrors'] ?? 0],
                ['Error-ledger entries this run', $summary['errors']],
                ['Note-ledger entries this run', $summary['notes']],
            ],
        );

        if (! empty($summary['windows'])) {
            $this->newLine();
            $this->info('Per-DB window (AnchorDate − N months):');
            $rows = [];
            foreach ($summary['windows'] as $db => $w) {
                $rows[] = [$db, $w['anchor'] ?? 'NONE', $w['windowStart'] ?? 'NONE', $w['months']];
            }
            $this->table(['Source DB', 'AnchorDate', 'Window Start', 'N (months)'], $rows);
        }

        if (! empty($summary['perTable'])) {
            $this->newLine();
            $this->info('Per-table breakdown:');
            $rows = [];
            foreach ($summary['perTable'] as $t) {
                $rows[] = [
                    $t['db'],
                    $t['dateColumn'] ?? '(full copy)',
                    $t['read'], $t['written'], $t['existing'], $t['errors'],
                    $t['fullCopy'] ? 'yes' : '',
                ];
            }
            $this->table(['DB', 'Date Column', 'Read', 'Inserted', 'Existing', 'Errors', 'FullCopy'], $rows);
        }

        if (! empty($summary['perChildTable'])) {
            $this->newLine();
            $this->info('Per-child-table breakdown (bigint-id children, task 8.2 — selected by loaded header set):');
            $rows = [];
            foreach ($summary['perChildTable'] as $t) {
                $rows[] = [
                    $t['db'], $t['parent'], $t['parentFk'],
                    $t['read'], $t['inserted'], $t['existing'], $t['errors'],
                ];
            }
            $this->table(['DB', 'Parent Header', 'Parent FK', 'Read', 'Inserted', 'Existing', 'Errors'], $rows);
        }

        $this->newLine();

        // Task 8.4 — single top-level idempotency verdict across ALL three
        // entities (headers, children, attendance). A clean no-op is the
        // provable property of a re-run (R14.4/R14.5): when NOTHING new was
        // inserted anywhere, the run changed no target state and the ledger did
        // not grow (every ledger write is an upsert by a stable key). The
        // per-entity "existing" counts above make the no-op observable.
        $headersInserted    = $summary['inserted'];
        $childrenInserted   = $summary['childrenInserted'] ?? 0;
        $attendanceInserted = $summary['attendanceInserted'] ?? 0;

        if ($headersInserted === 0 && $childrenInserted === 0 && $attendanceInserted === 0) {
            $this->info(
                'Idempotent re-run: 0 new headers, 0 new children, 0 new attendance — clean no-op (R14.4/R14.5)'
            );
            $this->line(sprintf(
                '  (existing this run — headers=%d, children=%d, attendance=%d; all guarded by ledger + live key, '
                .'so fusion_ledger does not grow beyond genuinely new source rows.)',
                $summary['existing'],
                $summary['childrenExisting'] ?? 0,
                $summary['attendanceExisting'] ?? 0,
            ));
        } else {
            $this->info(sprintf(
                'Done. Inserted %d new header(s), %d new child row(s), %d new attendance row(s) — each header carries '
                .'its VARCHAR id verbatim, children/attendance get a fresh identity, all stamped with company_id. '
                .'A re-run is a clean no-op (headers by id, children/attendance by natural key).',
                $headersInserted, $childrenInserted, $attendanceInserted,
            ));
        }
    }
}
