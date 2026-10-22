<?php

namespace App\Services\Fusion;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SafetyManager — the pre-load safety stage of estate-db-fusion.
 *
 * Spec authority:
 *   - design.md "Safety flow: backup + ordered truncate" sequence, LLD-10
 *     (SafetyManager::prepareTarget pseudocode), and the Error Handling table
 *     ("Backup missing or failed before truncate -> Abort entire pipeline; no
 *     truncate runs").
 *   - requirements.md Requirement 1 (all acceptance criteria R1.1-R1.9).
 *
 * This service holds the whole behaviour so it is unit/behaviour testable; the
 * `fusion:prepare-target` command ({@see \App\Console\Commands\Fusion\PrepareTargetCommand})
 * is a thin wrapper that wires options and output and calls {@see prepareTarget()}.
 *
 * ## Destructive-safety contract (R1)
 *
 * The stage refuses to do anything destructive unless, in strict order:
 *   1. `--confirm` was given                              (R1.1)
 *   2. the non-production guard detects dummy markers     (R1.2 / R1.3)
 *   3. a full timestamped backup of EPMS succeeds and the
 *      resulting .bak file is confirmed present           (R1.4 / R1.5)
 * Only then does it truncate in dependency order, retaining m_country, and
 * reseed identity (R1.6 / R1.7), reporting per-table cleaned counts (R1.8).
 *
 * At every pre-truncate failure point the method returns/throws BEFORE any
 * DELETE/TRUNCATE is issued, so "no truncate runs" is guaranteed.
 *
 * ## Non-production guard markers (R1.2 / R1.3) — documented and conservative
 *
 * The target must look like the seeded dummy data described in the spec. The
 * guard is deliberately conservative ("when in doubt, abort") and requires BOTH:
 *
 *   (a) an explicit operator gate — either APP_ENV=local OR the `--i-understand`
 *       option (surfaced here as $iUnderstand). Production (APP_ENV=production)
 *       is NEVER allowed, even with the flag.
 *   (b) at least one recognised dummy data marker actually present in the target:
 *         - m_company.company_code IN ('1TEST','2TEST','1TSK','2SGK'), OR
 *         - m_estate.estate_code  IN ('EST01','EST02','EST03'), OR
 *         - m_estate.estate_name  LIKE 'SAMPLE ESTATE%'
 *
 * If the real fused companies (1PIP, 1PWN, ...) are already present and none of
 * the dummy markers are, the guard treats the target as "not obviously dummy"
 * and aborts. This stops the command from ever truncating a loaded/real target.
 *
 * ## Backup path constraint (R1.4) — IMPORTANT
 *
 * `BACKUP DATABASE [EPMS] TO DISK = N'...'` executes ON THE SQL SERVER HOST, not
 * on the machine running artisan. The path must therefore be writable by the
 * SQL Server service account, not merely by the Laravel process. The directory
 * is configurable (command option / FUSION_BACKUP_DIR env); the default is a
 * server-local path. COPY_ONLY + INIT are used so the backup chain is not
 * disturbed and the timestamped file is self-contained.
 *
 * ## Truncate order (R1.6) — dependency ordered, derived from the live schema
 *
 * The order follows the design pipeline reversed for teardown:
 *     children/transactions  ->  masters  ->  RBAC  ->  tenant-meta
 * Tables are discovered from the live target information_schema and classified
 * by {@see TableTaxonomy}; the category decides the bucket and the bucket order
 * decides the teardown order. `m_country` is explicitly RETAINED (R1.7). Each
 * table prefers TRUNCATE (resets identity); on failure it falls back to DELETE +
 * `DBCC CHECKIDENT (..., RESEED, 0)` so identity is reseeded either way (R1.6).
 *
 * ## Separation from idempotent load stages (R1.9)
 *
 * This is a stand-alone, explicitly-confirmed command. It is NOT part of the
 * idempotent `fusion:run` orchestrator's re-runnable stages and must never run
 * as part of a re-run (see task 11 / design LLD-9: "The safety stage ... is a
 * separate, explicitly-confirmed command and is not part of idempotent re-runs").
 */
class SafetyManager
{
    /** The target connection name (SQL Server EPMS via ODBC). */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /** Country rows that must survive the truncate (R1.7). */
    public const RETAINED_TABLES = ['m_country'];

    /**
     * Recognised dummy company codes — a non-production marker (R1.2).
     * These are the seeded placeholder tenants, distinct from the real fused
     * companies (1PIP, 1PWN, 1PPP, 1LMS, 1LUD, 1PPM, 1PDP).
     */
    public const DUMMY_COMPANY_CODES = ['1TEST', '2TEST', '1TSK', '2SGK'];

    /** Recognised dummy estate codes — a non-production marker (R1.2). */
    public const DUMMY_ESTATE_CODES = ['EST01', 'EST02', 'EST03'];

    /** Recognised dummy estate-name prefix — a non-production marker (R1.2). */
    public const DUMMY_ESTATE_NAME_PREFIX = 'SAMPLE ESTATE';

    /**
     * Teardown bucket order (R1.6): transactions/children first, then masters,
     * then RBAC, then tenant-meta. Each entry maps to one or more
     * {@see TableCategory} values. m_country is removed from the tenant bucket
     * explicitly (R1.7).
     *
     * @var array<int, array{label:string, categories:array<int,TableCategory>}>
     */
    private const TEARDOWN_BUCKETS = [
        [
            'label' => 'transactions/children',
            'categories' => [
                TableCategory::BIGINT_ID_CHILD,
                TableCategory::ATTENDANCE_SPECIAL,
                TableCategory::VARCHAR_ID_HEADER,
            ],
        ],
        [
            'label' => 'masters',
            'categories' => [
                TableCategory::TENANT_MASTER,
                TableCategory::GLOBAL_MASTER,
            ],
        ],
        [
            'label' => 'RBAC',
            'categories' => [
                TableCategory::RBAC,
            ],
        ],
        [
            'label' => 'tenant',
            'categories' => [
                TableCategory::TENANT_META,
            ],
        ],
    ];

    private readonly ConnectionInterface $target;

    private readonly TableTaxonomy $taxonomy;

    public function __construct(?ConnectionInterface $target = null, ?TableTaxonomy $taxonomy = null)
    {
        $this->target = $target ?? DB::connection(config('fusion.target_connection', self::TARGET_CONNECTION));
        $this->taxonomy = $taxonomy ?? new TableTaxonomy($this->target);
    }

    /**
     * Run the full prepare-target safety flow.
     *
     * Returns a structured {@see SafetyReport}. Progress is reported through the
     * optional $progress callback (the command passes one that writes to the
     * console) so the service itself has no I/O dependency and stays testable.
     *
     * Order of guards is strict; the method returns an aborted report (and runs
     * NO truncate) the moment any guard fails.
     *
     * @param  bool  $confirmed    whether --confirm was supplied (R1.1).
     * @param  bool  $iUnderstand  explicit destructive gate; with APP_ENV this
     *                             forms the operator gate of the guard (R1.2).
     * @param  string  $backupDir  directory for the .bak file (server-writable).
     * @param  callable(string $level, string $message):void|null  $progress
     * @param  callable(string $sql):bool|null  $backupRunner  override for the
     *         actual BACKUP execution (tests inject a fake); defaults to a real
     *         unprepared statement on the target connection.
     * @param  callable(string $file):bool|null  $backupVerifier  override for the
     *         "is the .bak present?" check (tests inject a fake); defaults to a
     *         server-side RESTORE HEADERONLY probe.
     */
    public function prepareTarget(
        bool $confirmed,
        bool $iUnderstand,
        string $backupDir,
        ?callable $progress = null,
        ?callable $backupRunner = null,
        ?callable $backupVerifier = null,
    ): SafetyReport {
        $say = $progress ?? static fn (string $level, string $message) => null;

        // --- R1.1: --confirm is mandatory. No flag => abort, NO truncate. ---
        if (! $confirmed) {
            $say('error', 'Refusing to run: --confirm is required. No truncate was performed.');

            return SafetyReport::aborted('missing_confirm',
                '--confirm flag is required before any destructive action (R1.1).');
        }

        // --- R1.2 / R1.3: non-production guard. Missing markers => abort. ---
        $say('info', 'Running non-production guard (expecting dummy data markers)...');
        $guard = $this->guardNotProduction($iUnderstand);
        if (! $guard['passed']) {
            $say('error', 'Non-production guard FAILED: '.$guard['reason'].' No truncate was performed.');

            return SafetyReport::aborted('guard_failed', $guard['reason']);
        }
        $say('info', 'Guard passed: '.$guard['reason']);

        // --- R1.4 / R1.5: mandatory full backup. Fail/missing => abort. ---
        $backupFile = $this->backupFilePath($backupDir);
        $say('info', 'Backing up EPMS to '.$backupFile.' (runs on the SQL Server host)...');
        $backupOk = $this->backupDatabase($backupFile, $backupRunner, $backupVerifier, $say);
        if (! $backupOk) {
            $say('error', 'Backup failed or the .bak file is not confirmed. No truncate was performed.');

            return SafetyReport::aborted('backup_failed',
                'Backup missing or failed before truncate; pipeline halted (R1.5).', $backupFile);
        }
        $say('info', 'Backup confirmed: '.$backupFile);

        // --- R1.6 / R1.7 / R1.8: dependency-ordered truncate + reseed + counts. ---
        $order = $this->truncateOrder();
        $say('info', 'Truncating '.count($order).' table(s) in dependency order (m_country retained)...');

        $cleaned = [];
        foreach ($order as $table) {
            $before = $this->rowCount($table);
            $method = $this->truncateAndReseed($table, $say);
            $after = $this->rowCount($table);

            $cleaned[] = [
                'table' => $table,
                'before' => $before,
                'after' => $after,
                'cleaned' => max(0, $before - $after),
                'method' => $method,
            ];
            $say('info', sprintf('  %-32s %8d -> %-6d (%s)', $table, $before, $after, $method));
        }

        $say('info', 'Prepare-target complete. Retained: '.implode(', ', self::RETAINED_TABLES).'.');

        return SafetyReport::completed($backupFile, $cleaned);
    }

    /**
     * Non-production guard (R1.2 / R1.3).
     *
     * Conservative AND of an operator gate and the presence of a dummy marker.
     * Production is never allowed. Returns ['passed'=>bool,'reason'=>string].
     *
     * @return array{passed:bool, reason:string}
     */
    public function guardNotProduction(bool $iUnderstand): array
    {
        $env = (string) app()->environment();

        if (strtolower($env) === 'production') {
            return ['passed' => false, 'reason' => "APP_ENV is 'production' — destructive prepare-target is forbidden."];
        }

        // Operator gate: local env, or an explicit --i-understand acknowledgement.
        $gateOpen = strtolower($env) === 'local' || $iUnderstand;
        if (! $gateOpen) {
            return [
                'passed' => false,
                'reason' => sprintf(
                    "operator gate closed: APP_ENV is '%s' (not 'local') and --i-understand was not given.",
                    $env,
                ),
            ];
        }

        // Dummy-data markers: at least one must be present in the live target.
        $markers = $this->detectDummyMarkers();
        if (empty($markers)) {
            return [
                'passed' => false,
                'reason' => 'no dummy-data markers detected in the target '
                    .'(expected dummy m_company codes, EST01-03, or "SAMPLE ESTATE%").',
            ];
        }

        return [
            'passed' => true,
            'reason' => sprintf("env='%s', gate open, dummy markers found: %s", $env, implode('; ', $markers)),
        ];
    }

    /**
     * Probe the target for recognised dummy-data markers (R1.2). Each probe is
     * defensive: a missing table/column simply yields no marker rather than
     * throwing, so the guard degrades to "no marker -> abort" (conservative).
     *
     * @return array<int,string> human-readable descriptions of markers found.
     */
    public function detectDummyMarkers(): array
    {
        $markers = [];

        $dummyCompanies = $this->safeScalar(function () {
            return (int) $this->target->table('m_company')
                ->whereIn('company_code', self::DUMMY_COMPANY_CODES)
                ->count();
        });
        if ($dummyCompanies > 0) {
            $markers[] = $dummyCompanies.' dummy m_company code(s)';
        }

        $dummyEstates = $this->safeScalar(function () {
            return (int) $this->target->table('m_estate')
                ->whereIn('estate_code', self::DUMMY_ESTATE_CODES)
                ->count();
        });
        if ($dummyEstates > 0) {
            $markers[] = $dummyEstates.' dummy m_estate code(s)';
        }

        $sampleEstates = $this->safeScalar(function () {
            return (int) $this->target->table('m_estate')
                ->where('estate_name', 'like', self::DUMMY_ESTATE_NAME_PREFIX.'%')
                ->count();
        });
        if ($sampleEstates > 0) {
            $markers[] = $sampleEstates.' "SAMPLE ESTATE%" m_estate name(s)';
        }

        return $markers;
    }

    /**
     * Build the timestamped backup file path. The directory is server-writable
     * (see class doc, R1.4). Normalises to backslashes for a Windows SQL Server
     * host and never double-slashes the join.
     */
    public function backupFilePath(string $backupDir, ?\DateTimeInterface $at = null): string
    {
        $stamp = ($at ?? now())->format('Ymd_His');
        $dir = rtrim(str_replace('/', '\\', $backupDir), '\\');

        return $dir.'\\EPMS_pre_fusion_'.$stamp.'.bak';
    }

    /**
     * Perform the full SQL Server backup and confirm the .bak exists (R1.4/R1.5).
     *
     * Executes `BACKUP DATABASE [EPMS] TO DISK = N'...' WITH INIT, COPY_ONLY`
     * as an unprepared statement on the target connection (DDL-like; cannot be
     * parameterised). Returns false (never throws) when the BACKUP statement
     * fails OR the resulting file cannot be confirmed — the caller then aborts
     * with NO truncate. The runner/verifier are injectable for tests.
     *
     * @param  callable(string $sql):bool|null  $runner
     * @param  callable(string $file):bool|null  $verifier
     * @param  callable(string $level, string $message):void  $say
     */
    public function backupDatabase(
        string $backupFile,
        ?callable $runner = null,
        ?callable $verifier = null,
        ?callable $say = null,
    ): bool {
        $say ??= static fn (string $level, string $message) => null;

        // The target database name as the BACKUP target. [EPMS] is bracket-quoted;
        // the file path is single-quoted inside an N'' literal. The path comes
        // from a server-side option/env, not from untrusted request input.
        $database = (string) ($this->target->getDatabaseName() ?: 'EPMS');
        $safeFile = str_replace("'", "''", $backupFile);
        $sql = sprintf(
            "BACKUP DATABASE [%s] TO DISK = N'%s' WITH INIT, COPY_ONLY, FORMAT, "
            ."NAME = N'EPMS pre-fusion full backup'",
            str_replace(']', ']]', $database),
            $safeFile,
        );

        try {
            $ran = $runner
                ? (bool) $runner($sql)
                : $this->runUnprepared($sql);
        } catch (\Throwable $e) {
            $say('error', 'BACKUP statement threw: '.$e->getMessage());

            return false;
        }

        if (! $ran) {
            $say('error', 'BACKUP statement did not report success.');

            return false;
        }

        // Confirm the backup artifact actually exists (R1.5). Default probe asks
        // the server to read the header of the file it just wrote; tests inject
        // a fake verifier.
        try {
            $confirmed = $verifier
                ? (bool) $verifier($backupFile)
                : $this->verifyBackupOnServer($backupFile);
        } catch (\Throwable $e) {
            $say('error', 'Backup verification threw: '.$e->getMessage());

            return false;
        }

        if (! $confirmed) {
            $say('error', 'Backup file could not be confirmed on the server.');
        }

        return $confirmed;
    }

    /**
     * The dependency-ordered list of tables to truncate (R1.6), m_country
     * retained (R1.7). Discovered from the live target information_schema and
     * classified by {@see TableTaxonomy}: bucket order is the teardown order,
     * tables within a bucket are name-sorted for a stable, reproducible plan.
     *
     * fusion_ledger is intentionally NOT truncated here — it is the working
     * idempotency ledger, not dummy tenant data, and clearing it is a separate
     * operator concern.
     *
     * @return array<int,string>
     */
    public function truncateOrder(): array
    {
        $tables = $this->listTargetTables();

        // Classify every discovered table once.
        $byCategory = [];
        foreach ($tables as $table) {
            if (in_array($table, self::RETAINED_TABLES, true)) {
                continue; // R1.7 — never schedule m_country for truncate.
            }
            if ($table === 'fusion_ledger') {
                continue; // working ledger — not part of the dummy-data teardown.
            }

            $plan = $this->taxonomy->classify($table);
            $byCategory[$plan->category->value][] = $table;
        }

        $order = [];
        foreach (self::TEARDOWN_BUCKETS as $bucket) {
            $bucketTables = [];
            foreach ($bucket['categories'] as $category) {
                foreach ($byCategory[$category->value] ?? [] as $t) {
                    $bucketTables[] = $t;
                }
            }
            sort($bucketTables, SORT_STRING);
            foreach ($bucketTables as $t) {
                $order[] = $t;
            }
        }

        return $order;
    }

    /**
     * Truncate a single table with identity reseed (R1.6). Prefers TRUNCATE
     * (which also resets identity); on any failure (permissions, an FK
     * reference) falls back to DELETE + `DBCC CHECKIDENT (..., RESEED, 0)`.
     *
     * @param  callable(string $level, string $message):void  $say
     * @return string the method actually used: 'truncate' | 'delete+reseed' | 'delete'.
     */
    public function truncateAndReseed(string $table, ?callable $say = null): string
    {
        $say ??= static fn (string $level, string $message) => null;
        $quoted = '['.str_replace(']', ']]', $table).']';

        // Prefer TRUNCATE — fast, minimally logged, resets the identity seed.
        try {
            $this->runUnprepared('TRUNCATE TABLE '.$quoted);

            return 'truncate';
        } catch (\Throwable $e) {
            $say('warn', sprintf('TRUNCATE %s failed (%s); falling back to DELETE + reseed.', $table, $e->getMessage()));
        }

        // Fallback: DELETE then reseed identity to 0 so the next insert starts at 1.
        $this->runUnprepared('DELETE FROM '.$quoted);

        try {
            // CHECKIDENT RESEED only applies to tables that actually have an
            // identity column; it is a harmless no-op to attempt and we swallow
            // the "does not contain an identity column" message.
            $this->runUnprepared(sprintf("DBCC CHECKIDENT ('%s', RESEED, 0)", str_replace("'", "''", $table)));

            return 'delete+reseed';
        } catch (\Throwable $e) {
            $say('warn', sprintf('DBCC CHECKIDENT on %s skipped (%s).', $table, $e->getMessage()));

            return 'delete';
        }
    }

    /** COUNT(*) for a target table; defensive (missing table => 0). */
    public function rowCount(string $table): int
    {
        return $this->safeScalar(fn () => (int) $this->target->table($table)->count());
    }

    /**
     * List base tables in the target schema via information_schema.
     *
     * @return array<int,string>
     */
    private function listTargetTables(): array
    {
        $rows = $this->target->select(
            "SELECT table_name FROM information_schema.tables
             WHERE table_type = 'BASE TABLE'
             ORDER BY table_name"
        );

        $tables = [];
        foreach ($rows as $row) {
            // ODBC/SQL Server may return the column as table_name or TABLE_NAME.
            $name = $row->table_name ?? ($row->TABLE_NAME ?? null);
            if ($name !== null) {
                $tables[] = (string) $name;
            }
        }

        return $tables;
    }

    /** Execute a raw statement on the target connection (BACKUP / TRUNCATE / DBCC). */
    private function runUnprepared(string $sql): bool
    {
        return $this->target->unprepared($sql);
    }

    /**
     * Default server-side backup confirmation: ask SQL Server to read the
     * header of the file it just wrote. If RESTORE HEADERONLY returns a row the
     * .bak exists and is a valid backup set. Any error means "not confirmed".
     */
    private function verifyBackupOnServer(string $backupFile): bool
    {
        $safeFile = str_replace("'", "''", $backupFile);
        $rows = $this->target->select(
            sprintf("RESTORE HEADERONLY FROM DISK = N'%s'", $safeFile)
        );

        return ! empty($rows);
    }

    /**
     * Run a scalar-producing closure, returning 0 on any failure (missing
     * table/column). Keeps the guard and counts defensive so a schema that
     * lacks an expected table never crashes the safety flow.
     */
    private function safeScalar(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
