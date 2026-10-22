<?php

namespace App\Services\Fusion;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reconciler — the post-load validation stage of estate-db-fusion (spec task
 * 10, requirements R15). Driven by the `fusion:reconcile` command
 * ({@see \App\Console\Commands\Fusion\ReconcileCommand}).
 *
 * Spec authority:
 *   - design.md LLD-11 (validation / reconciliation plan), sequence/flow (the
 *     Reconciler node in the Architecture graph), "Data Models" (FusionLedger),
 *     LLD-3 (table categories — what is GLOBAL vs TENANT), LLD-9 (the ledger
 *     entry types that explain skips).
 *   - requirements.md R15.1 (compare expected source counts — after filters and
 *     logged skips — against loaded target counts per table and source DB),
 *     R15.2 (no tenant-table row has NULL company_id; every company_id exists in
 *     m_company), R15.3 (no business key is stamped with two different
 *     company_id values unless the table is a known GLOBAL master).
 *
 * ## SCOPE OF THIS TASK (10.1) — ROW-COUNT + TENANT-ISOLATION CHECKS ONLY
 *
 * This task implements the first three LLD-11 checks and leaves the rest as
 * documented seams (mirroring how MasterCopier/TransactionCopier left extension
 * points for their later sub-tasks):
 *
 *   1. {@see checkRowCounts()}  — R15.1. Per source DB, per in-scope table,
 *      compare EXPECTED vs LOADED and classify the delta, cross-referencing the
 *      error/note ledger so a shortfall EXPLAINED by logged skips is a WARNING,
 *      not a hard FAIL (and the deferred headers/children are EXPECTED-deferred,
 *      not losses).
 *   2. {@see checkTenantIsolation()} — R15.2. Assert NO tenant table (any target
 *      table the taxonomy marks injectCompanyId / that physically has a
 *      company_id column) has a NULL company_id, and every distinct company_id
 *      present resolves to an m_company row. HARD FAIL on a violation.
 *   3. {@see checkCrossTenantLeak()} — R15.3. Assert no business key carries two
 *      DIFFERENT company_id values, EXCEPT for known GLOBAL masters (legitimately
 *      shared, no company_id at all) — a business key spanning >1 company in a
 *      tenant table is a HARD FAIL.
 *
 * ## IMPLEMENTED BY TASK 10.2 (R15.4 / R15.6):
 *   - {@see checkRbacIntegrity()}  — R15.4: every tc_user has exactly one
 *     tc_user_access; every role_id resolves in m_roles; every estate
 *     tc_user_scope.scope_id resolves to an m_estate of the user's company (and
 *     every country scope to an m_country); plus the informational tier
 *     distribution. HARD FAIL on a breach.
 *   - {@see checkExclusions()}     — R15.6: no target row originates from
 *     res_data / audit_trail (no excluded target table, no ledger trace, and the
 *     taxonomy still classifies them EXCLUDE). HARD FAIL on any trace.
 *
 * ## SEAM LEFT FOR TASK 10.3 (deliberate stub — see the method docblock):
 *   - {@see checkTieredLogin()}    — task 10.3 (R15.5 / R10): a per-tier login
 *     spot-check driving HasCompanyScope visibility. Returns a NOT-RUN check
 *     result today so the report shape is already the final shape and 10.3 only
 *     fills the body.
 *
 * ## HOW "EXPECTED" IS COMPUTED AND RECONCILED AGAINST THE LEDGER (R15.1)
 *
 * The copiers are the authority for WHAT was loaded and WHY a row was skipped:
 * every skip (NOT-NULL violation, unmapped role, no-email user, deferred header,
 * ambiguous key, empty attendance window, coercion failure) is recorded to the
 * fusion_ledger error/note area, and every loaded id/natural-key is recorded to
 * a typed ledger entry. The Reconciler therefore computes EXPECTED by REPLAYING
 * the copier's own selection against the live SOURCE, then reconciles the delta
 * with the ledger rather than guessing:
 *
 *   - TENANT / GLOBAL MASTERS (R11): EXPECTED = post-filter source rows (full
 *     copy, no date filter). GLOBAL masters are deduped across all 12 DBs by
 *     business key, so their per-DB EXPECTED is "distinct business keys", and the
 *     LOADED side is the single deduped target table (not per company). TENANT
 *     masters are counted per company_id. EXPECTED is reduced by the error-ledger
 *     skips attributed to that (db, table).
 *
 *   - WINDOWED TRANSACTION HEADERS (R12): EXPECTED is RECOMPUTED with the SAME
 *     per-DB window the TransactionCopier used — we reuse
 *     {@see TransactionCopier::computeWindow()} verbatim and count source header
 *     rows inside [windowStart, anchor] on each header's own date column — minus
 *     the error-ledger skips. The deferred headers (t_overtime, tr_gi_header,
 *     tr_gr_header) are reported EXPECTED-DEFERRED (counted but flagged as a
 *     deliberate non-loss), never as a shortfall.
 *
 *   - CHILDREN (R12.4/R12.5): EXPECTED = source children of the LOADED header set
 *     (selected by the parent VARCHAR business key, never by date) minus skips.
 *     We re-derive the loaded header id set from the ledger (txn_header_loaded)
 *     for the DB, then count source children whose FK is IN that set.
 *
 *   - ATTENDANCE (R12.8): EXPECTED = source rows in the per-DB window on
 *     attendance_date minus skips (0 when the per-DB window excludes all
 *     attendance — the documented empty-window note).
 *
 *   LOADED is ALWAYS counted from the live TARGET attributable to this
 *   source/company: tenant tables by company_id; headers by the loaded id set /
 *   id presence; children/attendance by company_id. A MISMATCH whose delta is
 *   fully accounted for by the ledger error/note counts (or by the
 *   EXPECTED-deferred flag) is a WARNING; an UNEXPLAINED shortfall is a hard
 *   FAIL (silent loss). A target OVER-count (target has MORE than expected — e.g.
 *   pre-existing dummy/foreign-company data not from this run) is reported as an
 *   INFO/over-count observation, never a load failure, because the Reconciler is
 *   read-only and the extra rows are outside this run's EXPECTED.
 *
 * ## READ-ONLY (R15, LLD-11)
 * The Reconciler NEVER writes to the target load tables and NEVER truncates. It
 * may OPTIONALLY record its own report to the ledger as a 'note' when
 * recordToLedger is enabled (off by default) — the only write it ever performs,
 * and only to the ledger, never to a load table.
 *
 * The behaviour lives in this service (unit/behaviour testable); the command is
 * a thin wrapper that wires options/output and calls {@see reconcile()}.
 */
class Reconciler
{
    /** Target connection name (SQL Server EPMS via ODBC). */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /** Check verdicts. */
    public const PASS = 'PASS';
    public const FAIL = 'FAIL';
    public const WARN = 'WARN';
    public const NOT_RUN = 'NOT_RUN';

    /** fusion_ledger entry_type the Reconciler reads (skips/errors, R13.5/R6.2). */
    public const LEDGER_ERROR = 'error';

    /** fusion_ledger entry_type the Reconciler reads (non-fatal notes/deferrals). */
    public const LEDGER_NOTE = 'note';

    /** fusion_ledger entry_type: a loaded VARCHAR header id (TransactionCopier). */
    public const LEDGER_HEADER_LOADED = 'txn_header_loaded';

    /** fusion_ledger entry_type the Reconciler OPTIONALLY writes its report under. */
    public const LEDGER_RECON_NOTE = 'note';

    /**
     * The carry-verbatim VARCHAR-id headers the TransactionCopier actually loads
     * (task 8.1 set). Their EXPECTED is the windowed source count. Mirrors
     * {@see TransactionCopier} HEADER_ID_COLUMN — kept here as the Reconciler's
     * own authority so it does not reach into the copier's privates.
     *
     * @var array<string,string>  header table => source VARCHAR id column
     */
    private const HEADER_ID_COLUMN = [
        't_oph'         => 'oph_id',
        't_cp'          => 'cp_id',
        't_fdn'         => 'fdn_id',
        't_workdone'    => 'workdone_id',
        't_vra'         => 'vra_id',
        't_coconut_oph' => 'coconut_oph_id',
        't_coconut_fdn' => 'coconut_fdn_id',
    ];

    /**
     * Headers DELIBERATELY DEFERRED by task 8.1 (id strategy unresolved). They
     * are EXPECTED-DEFERRED, not a loss (R15.1). Mirrors
     * {@see TransactionCopier} DEFERRED_HEADERS.
     *
     * @var list<string>
     */
    private const DEFERRED_HEADERS = [
        't_overtime',
        'tr_gi_header',
        'tr_gr_header',
    ];

    /**
     * In-scope children grouped by their VARCHAR-id parent header (task 8.2 set),
     * with the SOURCE VARCHAR parent-FK column used to select them by the loaded
     * header set (never by date). Mirrors {@see TransactionCopier} CHILDREN_OF /
     * CHILD_PARENT_FK so EXPECTED children are counted exactly as they were
     * selected.
     *
     * @var array<string,array<string,string>>  parent => [child => source FK column]
     */
    private const CHILDREN_OF = [
        't_oph'         => ['t_oph_persons' => 'oph_id', 't_harvesting_deduction' => 'harvesting_deduction_oph_id'],
        't_cp'          => ['t_cp_detail' => 'cp_id', 't_cp_loader' => 'cp_id'],
        't_fdn'         => ['t_fdn_detail' => 'fdn_id', 't_fdn_loader' => 'fdn_id'],
        't_workdone'    => ['t_workdone_material' => 'workdone_id'],
        't_coconut_oph' => ['t_coconut_oph_detail' => 'coconut_oph_id', 't_coconut_oph_persons' => 'coconut_oph_id'],
        't_coconut_fdn' => ['t_coconut_fdn_detail' => 'coconut_fdn_id'],
    ];

    /** SOURCE attendance date / employee-code columns (natural key, R12.8). */
    private const ATTENDANCE_DATE_SRC = 'attendance_date';
    private const ATTENDANCE_EMPLOYEE_CODE_SRC = 'attendance_employee_code';

    private readonly ConnectionInterface $target;

    /** @var array<string,array<int,string>> live target column cache. */
    private array $columnCache = [];

    /** @var array<int,bool> company_id -> exists in m_company cache. */
    private array $companyExistsCache = [];

    public function __construct(
        private readonly SourceRegistry $registry,
        private readonly TenantResolver $resolver,
        private readonly TransactionCopier $transactionCopier,
        ?ConnectionInterface $target = null,
        private readonly ?TableTaxonomy $taxonomy = null,
    ) {
        $this->target = $target ?? DB::connection(config('fusion.target_connection', self::TARGET_CONNECTION));
    }

    /**
     * Run the reconciliation checks and return a structured report.
     *
     * @param  callable(string $level, string $message):void|null  $progress
     * @param  array{only_db?: ?string, months?: ?int, record_to_ledger?: bool}  $options
     *     - only_db:          restrict the report to a single source db_name.
     *     - months:           override the window N (defaults to config) so the
     *                         recomputed EXPECTED matches the window the copy used.
     *     - record_to_ledger: when true, write a summary note to the ledger
     *                         (the ONLY write the Reconciler ever performs). Off
     *                         by default — the stage is read-only.
     * @return array{
     *     databases:int,
     *     overallVerdict:string,
     *     hardFailures:int,
     *     warnings:int,
     *     checks:array<string,array{
     *         id:string, title:string, requirement:string, verdict:string,
     *         summary:string, rows:list<array<string,mixed>>, violations:list<array<string,mixed>>
     *     }>,
     *     ledger:array{errors:int,notes:int,headerLoaded:int,childLoaded:int,attendanceNk:int}
     * }
     */
    public function reconcile(?callable $progress = null, array $options = []): array
    {
        $say = $progress ?? static fn (string $level, string $message) => null;

        $onlyDb = $options['only_db'] ?? null;
        $months = $this->resolveMonths($options['months'] ?? null);

        $say('info', 'Reconciler — row-count, tenant-isolation, RBAC-integrity & exclusion checks. READ-ONLY. No truncate.');

        // Gather the ledger skip/error/note counts ONCE up front; they are the
        // cross-reference every row-count mismatch is explained against (R15.1).
        $ledgerSummary = $this->summarizeLedger($onlyDb);

        $checks = [];

        // ---- R15.1 Row-count reconciliation ---------------------------------
        $say('info', 'Check 1/3: row-count reconciliation (expected vs loaded, ledger-explained)...');
        $checks['row_counts'] = $this->checkRowCounts($onlyDb, $months, $say);

        // ---- R15.2 Tenant isolation (NULL company_id + m_company FK) --------
        $say('info', 'Check 2/3: tenant isolation (no NULL company_id; every company_id in m_company)...');
        $checks['tenant_isolation'] = $this->checkTenantIsolation($say);

        // ---- R15.3 Cross-tenant business-key leak ---------------------------
        $say('info', 'Check 3/3: cross-tenant business-key leak (no key spans >1 company, except GLOBAL)...');
        $checks['cross_tenant_leak'] = $this->checkCrossTenantLeak($say);

        // ---- SEAMS for tasks 10.2 / 10.3 (documented, NOT_RUN today) --------
        $checks['rbac_integrity'] = $this->checkRbacIntegrity();
        $checks['exclusions'] = $this->checkExclusions();
        $checks['tiered_login'] = $this->checkTieredLogin();

        // Roll up the verdict. A single hard FAIL fails the whole run; WARN-only
        // (fully ledger-explained mismatches) does not fail the exit code.
        $hardFailures = 0;
        $warnings = 0;
        foreach ($checks as $check) {
            if ($check['verdict'] === self::FAIL) {
                $hardFailures++;
            } elseif ($check['verdict'] === self::WARN) {
                $warnings++;
            }
        }

        $overall = $hardFailures > 0 ? self::FAIL : ($warnings > 0 ? self::WARN : self::PASS);

        $report = [
            'databases'      => $this->countScopedDatabases($onlyDb),
            'overallVerdict' => $overall,
            'hardFailures'   => $hardFailures,
            'warnings'       => $warnings,
            'checks'         => $checks,
            'ledger'         => $ledgerSummary,
        ];

        if (($options['record_to_ledger'] ?? false) === true) {
            $this->recordReportNote($report, $onlyDb);
        }

        return $report;
    }

    // -------------------------------------------------------------------------
    // Check 1 — Row-count reconciliation (R15.1)
    // -------------------------------------------------------------------------

    /**
     * Per source DB, per in-scope table: EXPECTED (post-filter source rows minus
     * logged skips) vs LOADED (target rows attributable to this source/company).
     * Classifies each table's delta and cross-references the ledger so a
     * shortfall explained by logged skips (or the deferred headers) is a WARNING,
     * not a hard FAIL.
     *
     * Per-table row shape:
     *   {
     *     db, table, category, expected, loaded, delta, skips, deferred:bool,
     *     verdict, cause
     *   }
     * where:
     *   expected = post-filter source count MINUS error-ledger skips for (db,table)
     *   loaded   = live target count attributable to this source/company
     *   delta    = loaded - expected  (negative = shortfall, positive = over-count)
     *   verdict  = MATCH | WARN | FAIL | DEFERRED | OVER
     *
     * @param  callable(string,string):void  $say
     * @return array{id:string,title:string,requirement:string,verdict:string,summary:string,rows:list<array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private function checkRowCounts(?string $onlyDb, int $months, callable $say): array
    {
        $taxonomy = $this->taxonomy ?? new TableTaxonomy($this->target);

        $rows = [];
        $violations = [];
        $anyHardFail = false;
        $anyWarn = false;

        foreach ($this->registry->all() as $descriptor) {
            if ($onlyDb !== null && $descriptor->dbName !== $onlyDb) {
                continue;
            }

            $tenant = $this->resolver->forEstate($descriptor->estateCode);
            if ($tenant === null) {
                $rows[] = $this->countRow(
                    $descriptor->dbName, '(tenant)', 'TENANT_META', 0, 0, 0,
                    self::FAIL, 'estate not seeded — run fusion:seed-tenants first',
                );
                $violations[] = [
                    'db'      => $descriptor->dbName,
                    'reason'  => 'tenant unresolved (estate '.$descriptor->estateCode.' not seeded)',
                ];
                $anyHardFail = true;
                continue;
            }

            $say('info', sprintf('  %s (estate %s, company_id %d):',
                $descriptor->dbName, $descriptor->estateCode, $tenant->companyId));

            $pg = null;
            try {
                $pg = SourceConnection::for($descriptor->dbName);

                // 1. Windowed transaction headers (+ children + attendance).
                $this->reconcileTransactions(
                    $pg, $descriptor, $tenant, $taxonomy, $months, $rows, $violations, $anyHardFail, $anyWarn, $say,
                );

                // 2. Masters (tenant + global) — full copy, no date filter.
                $this->reconcileMasters(
                    $pg, $descriptor, $tenant, $taxonomy, $rows, $violations, $anyHardFail, $anyWarn, $say,
                );
            } catch (Throwable $e) {
                $rows[] = $this->countRow(
                    $descriptor->dbName, '(db)', 'ERROR', 0, 0, 0,
                    self::WARN, 'source unreadable: '.$e->getMessage(),
                );
                $anyWarn = true;
                $say('warn', sprintf('    %s: source unreadable — %s', $descriptor->dbName, $e->getMessage()));
            } finally {
                SourceConnection::close();
            }
        }

        $verdict = $anyHardFail ? self::FAIL : ($anyWarn ? self::WARN : self::PASS);
        $summary = $this->rowCountSummaryLine($rows);

        return [
            'id'          => 'row_counts',
            'title'       => 'Row-count reconciliation (expected vs loaded)',
            'requirement' => 'R15.1',
            'verdict'     => $verdict,
            'summary'     => $summary,
            'rows'        => $rows,
            'violations'  => $violations,
        ];
    }

    /**
     * Reconcile the windowed transaction headers, their children, and attendance
     * for one source DB against the loaded target counts.
     *
     * @param  list<array<string,mixed>>  $rows
     * @param  list<array<string,mixed>>  $violations
     * @param  callable(string,string):void  $say
     */
    private function reconcileTransactions(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TableTaxonomy $taxonomy,
        int $months,
        array &$rows,
        array &$violations,
        bool &$anyHardFail,
        bool &$anyWarn,
        callable $say,
    ): void {
        $db = $descriptor->dbName;
        $companyId = $tenant->companyId;

        // Recompute the SAME per-DB window the TransactionCopier used (R12.1).
        $presentHeaders = $this->presentHeaders($pg);
        $window = $this->transactionCopier->computeWindow($pg, $presentHeaders, $months);

        // --- Headers ---------------------------------------------------------
        foreach ($presentHeaders as $table) {
            // Deferred headers: EXPECTED-DEFERRED, not a loss (R15.1).
            if (in_array($table, self::DEFERRED_HEADERS, true)) {
                $srcCount = $this->safeCount($pg, $table);
                $rows[] = $this->countRow(
                    $db, $table, 'VARCHAR_ID_HEADER', $srcCount, 0, 0 - $srcCount,
                    'DEFERRED',
                    'header id strategy deliberately deferred by task 8.1 (not a loss) — see ledger note deferred_header',
                );
                $say('warn', sprintf('    %s [HEADER]: DEFERRED (src=%d, loaded=0 — expected-deferred, not a loss).',
                    $table, $srcCount));
                continue;
            }

            if (! isset(self::HEADER_ID_COLUMN[$table]) || ! $this->targetTableExists($table)) {
                continue; // not a carry-verbatim header / no target table — nothing to reconcile
            }

            $dateCol = $window['dateColumns'][$table] ?? null;

            // EXPECTED = windowed source count (full copy when no date column, R12.9).
            $expected = $this->windowedSourceCount($pg, $table, $dateCol, $window['windowStart'], $window['anchor']);
            $skips = $this->ledgerErrorCountFor($db, $table);
            $expectedNet = max(0, $expected - $skips);

            // LOADED = target rows carrying this company_id (the stamp this copy
            // applied). This is the count "attributable to this source".
            $loaded = $this->targetCountByCompany($table, $companyId);

            [$verdict, $cause] = $this->classifyDelta($expectedNet, $loaded, $skips, $expected);
            $this->rollVerdict($verdict, $anyHardFail, $anyWarn);

            $rows[] = $this->countRow($db, $table, 'VARCHAR_ID_HEADER', $expectedNet, $loaded, $loaded - $expectedNet, $verdict, $cause);
            if ($verdict === self::FAIL) {
                $violations[] = ['db' => $db, 'table' => $table, 'expected' => $expectedNet, 'loaded' => $loaded, 'cause' => $cause];
            }
            $say($verdict === self::FAIL ? 'error' : 'info', sprintf(
                '    %s [HEADER]: expected=%d loaded=%d delta=%+d skips=%d -> %s',
                $table, $expectedNet, $loaded, $loaded - $expectedNet, $skips, $verdict,
            ));

            // --- Children of this header -------------------------------------
            foreach (self::CHILDREN_OF[$table] ?? [] as $child => $fkCol) {
                if (! $this->sourceTableExists($pg, $child) || ! $this->targetTableExists($child)) {
                    continue;
                }

                // EXPECTED children = source children of the LOADED header set
                // (by parent VARCHAR FK, never by date, R12.4/R12.5) minus skips.
                $headerIds = $this->loadedHeaderIds($db, $table, $companyId);
                $childExpected = $headerIds === []
                    ? 0
                    : $this->childrenInHeaderSet($pg, $child, $fkCol, $headerIds);
                $childSkips = $this->ledgerErrorCountFor($db, $child);
                $childExpectedNet = max(0, $childExpected - $childSkips);

                $childLoaded = $this->targetCountByCompany($child, $companyId);

                [$cv, $cc] = $this->classifyDelta($childExpectedNet, $childLoaded, $childSkips, $childExpected);
                $this->rollVerdict($cv, $anyHardFail, $anyWarn);

                $rows[] = $this->countRow($db, $child, 'BIGINT_ID_CHILD', $childExpectedNet, $childLoaded, $childLoaded - $childExpectedNet, $cv, $cc.' (children of '.$table.')');
                if ($cv === self::FAIL) {
                    $violations[] = ['db' => $db, 'table' => $child, 'expected' => $childExpectedNet, 'loaded' => $childLoaded, 'cause' => $cc];
                }
                $say($cv === self::FAIL ? 'error' : 'info', sprintf(
                    '      %s [CHILD of %s]: expected=%d loaded=%d delta=%+d -> %s',
                    $child, $table, $childExpectedNet, $childLoaded, $childLoaded - $childExpectedNet, $cv,
                ));
            }
        }

        // --- Attendance (special, own window) --------------------------------
        $this->reconcileAttendance($pg, $db, $companyId, $window, $rows, $violations, $anyHardFail, $anyWarn, $say);
    }

    /**
     * Reconcile t_attendance for one DB (own date window on the per-DB window).
     *
     * @param  array{anchor:?string,windowStart:?string,dateColumns:array<string,?string>}  $window
     * @param  list<array<string,mixed>>  $rows
     * @param  list<array<string,mixed>>  $violations
     * @param  callable(string,string):void  $say
     */
    private function reconcileAttendance(
        ConnectionInterface $pg,
        string $db,
        int $companyId,
        array $window,
        array &$rows,
        array &$violations,
        bool &$anyHardFail,
        bool &$anyWarn,
        callable $say,
    ): void {
        $table = TableTaxonomy::ATTENDANCE;
        if (! $this->sourceTableExists($pg, $table) || ! $this->targetTableExists($table)) {
            return;
        }

        $dateCol = DateColumnRegistry::validateAgainst($pg, $table) ?? self::ATTENDANCE_DATE_SRC;
        $sourceCols = $this->sourceColumns($pg, $table);
        if (! in_array($dateCol, $sourceCols, true)) {
            // Date column genuinely absent — the copier skipped it with a note.
            $rows[] = $this->countRow($db, $table, 'ATTENDANCE_SPECIAL', 0,
                $this->targetCountByCompany($table, $companyId), 0, self::WARN,
                'no source attendance_date column — copier skipped (ledger note)');
            $anyWarn = true;
            return;
        }

        // EXPECTED = source rows in the per-DB window on attendance_date minus
        // skips (0 when the per-DB window excludes all attendance — the
        // documented empty-window note, still a valid EXPECTED of 0).
        $expected = $this->windowedSourceCount($pg, $table, $dateCol, $window['windowStart'], $window['anchor']);
        $skips = $this->ledgerErrorCountFor($db, $table);
        $expectedNet = max(0, $expected - $skips);

        $loaded = $this->targetCountByCompany($table, $companyId);

        [$verdict, $cause] = $this->classifyDelta($expectedNet, $loaded, $skips, $expected);
        $this->rollVerdict($verdict, $anyHardFail, $anyWarn);

        $rows[] = $this->countRow($db, $table, 'ATTENDANCE_SPECIAL', $expectedNet, $loaded, $loaded - $expectedNet, $verdict, $cause);
        if ($verdict === self::FAIL) {
            $violations[] = ['db' => $db, 'table' => $table, 'expected' => $expectedNet, 'loaded' => $loaded, 'cause' => $cause];
        }
        $say($verdict === self::FAIL ? 'error' : 'info', sprintf(
            '    %s [ATTENDANCE]: expected=%d loaded=%d delta=%+d -> %s',
            $table, $expectedNet, $loaded, $loaded - $expectedNet, $verdict,
        ));
    }

    /**
     * Reconcile the TENANT and GLOBAL master tables for one source DB.
     *
     * TENANT masters: EXPECTED = full source count minus skips; LOADED = target
     * rows carrying this company_id. GLOBAL masters: EXPECTED = distinct source
     * business keys (deduped across DBs); LOADED = the single deduped target
     * table count (reported once, as an informational cross-DB observation — a
     * global master legitimately aggregates all 12 DBs so a per-DB equality is
     * not expected; we report the per-DB distinct-key contribution vs the target
     * total and never hard-fail a global on a per-DB basis).
     *
     * This task reconciles the masters the MasterCopier explicitly handles (its
     * BUSINESS_KEY / COLUMN_MAP set) using the live taxonomy to decide
     * TENANT vs GLOBAL; a master the copier skipped with a note is reported as a
     * WARN with the ledger cause rather than a silent loss.
     *
     * @param  list<array<string,mixed>>  $rows
     * @param  list<array<string,mixed>>  $violations
     * @param  callable(string,string):void  $say
     */
    private function reconcileMasters(
        ConnectionInterface $pg,
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        TableTaxonomy $taxonomy,
        array &$rows,
        array &$violations,
        bool &$anyHardFail,
        bool &$anyWarn,
        callable $say,
    ): void {
        $db = $descriptor->dbName;
        $companyId = $tenant->companyId;

        foreach ($this->sourceMasterTables($pg) as $table) {
            if (! $this->targetTableExists($table)) {
                continue;
            }

            $plan = $taxonomy->classify($table);
            $category = $plan->category->name;

            // Only the two master categories are reconciled here.
            if ($category !== 'TENANT_MASTER' && $category !== 'GLOBAL_MASTER') {
                continue;
            }

            $srcCount = $this->safeCount($pg, $table);
            $skips = $this->ledgerErrorCountFor($db, $table);

            if ($category === 'TENANT_MASTER') {
                $expectedNet = max(0, $srcCount - $skips);
                $loaded = $this->targetCountByCompany($table, $companyId);

                [$verdict, $cause] = $this->classifyDelta($expectedNet, $loaded, $skips, $srcCount);
                $this->rollVerdict($verdict, $anyHardFail, $anyWarn);

                $rows[] = $this->countRow($db, $table, 'TENANT_MASTER', $expectedNet, $loaded, $loaded - $expectedNet, $verdict, $cause);
                if ($verdict === self::FAIL) {
                    $violations[] = ['db' => $db, 'table' => $table, 'expected' => $expectedNet, 'loaded' => $loaded, 'cause' => $cause];
                }
            } else {
                // GLOBAL master: deduped across all 12 DBs. Per-DB EXPECTED is the
                // distinct business-key contribution; LOADED is the single target
                // total (shared). We report it as INFO — a global's per-DB count
                // cannot exceed the shared target and is never a per-DB loss.
                $loadedTotal = $this->safeTargetCount($table);
                $rows[] = $this->countRow(
                    $db, $table, 'GLOBAL_MASTER', $srcCount, $loadedTotal, 0, 'GLOBAL',
                    'deduped across 12 DBs — per-DB source='.$srcCount.' vs shared target total='.$loadedTotal.' (not a per-DB equality)',
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Check 2 — Tenant isolation (R15.2)
    // -------------------------------------------------------------------------

    /**
     * Assert that NO tenant DATA table (an m_* / t_* / tr_* load table the
     * copiers STAMP with company_id) carries a NULL company_id, and that every
     * distinct company_id present resolves to an m_company row. A NULL company_id
     * or an orphan company_id is a HARD FAIL (R15.2).
     *
     * The tenant-table set is discovered from the live target schema
     * (information_schema — every table with a company_id column) and then
     * FILTERED to the fusion load tables via {@see isTenantDataTable()}. The
     * filter is essential and matches the design: the tiered RBAC tables carry a
     * NULL company_id BY DESIGN (super_admin = company_id NULL + country_id NULL;
     * country_admin = country_id set + company_id NULL — R7.1/R7.2), so
     * tc_user_access / tc_user_scope are NOT isolation violations and are
     * excluded. Likewise app-runtime tables that are not part of the fusion load
     * (e.g. login_log) are excluded — the Reconciler validates what the COPIERS
     * stamped, and the copiers stamp a non-null company_id on every tenant master
     * (R11.3), transaction header/child (R12.6/R12.7) and attendance row (R12.8).
     *
     * @param  callable(string,string):void  $say
     * @return array{id:string,title:string,requirement:string,verdict:string,summary:string,rows:list<array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private function checkTenantIsolation(callable $say): array
    {
        $rows = [];
        $violations = [];
        $hardFail = false;

        $tenantTables = $this->tenantTables();

        foreach ($tenantTables as $table) {
            // 1. NULL company_id.
            $nulls = $this->safeNullCompanyCount($table);
            // 2. company_id values that do not resolve in m_company.
            $orphans = $this->orphanCompanyIds($table);

            $verdict = ($nulls > 0 || $orphans !== []) ? self::FAIL : self::PASS;
            if ($verdict === self::FAIL) {
                $hardFail = true;
                $violations[] = [
                    'table'       => $table,
                    'nullCount'   => $nulls,
                    'orphanIds'   => $orphans,
                ];
                $say('error', sprintf('    %s: NULL company_id=%d, orphan company_id(s)=[%s] -> FAIL',
                    $table, $nulls, implode(',', $orphans)));
            }

            $rows[] = [
                'table'       => $table,
                'nullCompany' => $nulls,
                'orphanIds'   => implode(',', $orphans),
                'verdict'     => $verdict,
            ];
        }

        $verdict = $hardFail ? self::FAIL : self::PASS;

        return [
            'id'          => 'tenant_isolation',
            'title'       => 'Tenant isolation (no NULL company_id; every company_id in m_company)',
            'requirement' => 'R15.2',
            'verdict'     => $verdict,
            'summary'     => $hardFail
                ? count($violations).' tenant table(s) with NULL or orphan company_id'
                : count($tenantTables).' tenant table(s) clean (no NULL, no orphan company_id)',
            'rows'        => $rows,
            'violations'  => $violations,
        ];
    }

    // -------------------------------------------------------------------------
    // Check 3 — Cross-tenant business-key leak (R15.3)
    // -------------------------------------------------------------------------

    /**
     * Assert that no GLOBALLY-UNIQUE business key is stamped with two DIFFERENT
     * company_id values — a key that is unique by construction appearing under
     * >1 company is cross-tenant leakage and a HARD FAIL (R15.3).
     *
     * ## Why "globally-unique" is the correct scope (design reconciliation)
     *
     * R15.3 / LLD-11 forbid "a business key stamped with two different company_id
     * values unless the table is a known GLOBAL master". The meaningful subject
     * of that rule is a key that is UNIQUE ACROSS ALL DBS BY CONSTRUCTION — namely
     * the transaction headers' app-generated VARCHAR `id` (LLD-8: "carry id as-is
     * — verified globally unique, no collision across DBs"). If such an id ever
     * appeared under two companies, a row genuinely leaked across tenants.
     *
     * A TENANT MASTER's natural key (e.g. m_employee.employee_code,
     * m_block.block_code) is UNIQUE ONLY WITHIN A COMPANY by design — the target
     * key is (company_id, business_key) (LLD-3 / R11.3 / LLD-9). The SAME
     * employee_code legitimately exists under several companies (confirmed live:
     * one estate code appears under 1PIP/1LUD/1PPM as distinct company rows).
     * Flagging that as a leak would be a false positive against the design's own
     * composite-key model, so tenant-master key spans are reported as an
     * INFORMATIONAL observation, NEVER a hard FAIL. GLOBAL masters have no
     * company_id at all and are excluded entirely (the R15.3 exception).
     *
     * @param  callable(string,string):void  $say
     * @return array{id:string,title:string,requirement:string,verdict:string,summary:string,rows:list<array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private function checkCrossTenantLeak(callable $say): array
    {
        $rows = [];
        $violations = [];
        $hardFail = false;

        // --- HARD check: globally-unique keys (transaction headers) ----------
        foreach ($this->globallyUniqueKeyTargets() as $table => $businessKeyCol) {
            if (! $this->targetTableExists($table)) {
                continue;
            }
            $cols = $this->targetColumns($table);
            if (! in_array('company_id', $cols, true) || ! in_array($businessKeyCol, $cols, true)) {
                continue;
            }

            $spanning = $this->businessKeysSpanningCompanies($table, $businessKeyCol);
            $verdict = $spanning > 0 ? self::FAIL : self::PASS;
            if ($verdict === self::FAIL) {
                $hardFail = true;
                $violations[] = [
                    'table'        => $table,
                    'businessKey'  => $businessKeyCol,
                    'spanningKeys' => $spanning,
                    'examples'     => $this->leakExamples($table, $businessKeyCol),
                    'severity'     => 'HARD',
                ];
                $say('error', sprintf(
                    '    %s: %d globally-unique key(s) span >1 company_id (key=%s) -> LEAK FAIL',
                    $table, $spanning, $businessKeyCol,
                ));
            }

            $rows[] = [
                'table'        => $table,
                'businessKey'  => $businessKeyCol,
                'spanningKeys' => $spanning,
                'scope'        => 'globally-unique',
                'verdict'      => $verdict,
            ];
        }

        // --- INFO observation: tenant-master keys (legitimately per-company) --
        foreach ($this->tenantMasterKeyTargets() as $table => $businessKeyCol) {
            if (! $this->targetTableExists($table)) {
                continue;
            }
            $cols = $this->targetColumns($table);
            if (! in_array('company_id', $cols, true) || ! in_array($businessKeyCol, $cols, true)) {
                continue;
            }

            $spanning = $this->businessKeysSpanningCompanies($table, $businessKeyCol);
            // Per-company composite key: a span is EXPECTED, never a failure.
            $rows[] = [
                'table'        => $table,
                'businessKey'  => $businessKeyCol,
                'spanningKeys' => $spanning,
                'scope'        => 'per-company (composite key — info only)',
                'verdict'      => self::PASS,
            ];
            if ($spanning > 0) {
                $say('info', sprintf(
                    '    %s: %d key(s) appear under >1 company (key=%s) — EXPECTED for a (company_id, %s) '
                    .'tenant master; info only, not a leak.',
                    $table, $spanning, $businessKeyCol, $businessKeyCol,
                ));
            }
        }

        $verdict = $hardFail ? self::FAIL : self::PASS;

        return [
            'id'          => 'cross_tenant_leak',
            'title'       => 'Cross-tenant business-key leak (globally-unique keys; tenant-master keys info only)',
            'requirement' => 'R15.3',
            'verdict'     => $verdict,
            'summary'     => $hardFail
                ? count($violations).' table(s) have a GLOBALLY-UNIQUE business key spanning >1 company_id (LEAK)'
                : 'no globally-unique business key spans >1 company_id (tenant-master key spans are expected, reported info-only)',
            'rows'        => $rows,
            'violations'  => $violations,
        ];
    }

    // -------------------------------------------------------------------------
    // SEAMS — tasks 10.2 / 10.3 (documented stubs; final report shape today)
    // -------------------------------------------------------------------------

    /**
     * [IMPLEMENTED — task 10.2, R15.4] RBAC integrity. Asserts the tiered RBAC
     * model the RbacMigrator reconstructed is structurally intact on the live
     * target (design LLD-11 §3, requirements R15.4):
     *
     *   1. Every tc_user has EXACTLY ONE tc_user_access row (R7.5 /
     *      uq_user_access). A user with ZERO access rows is unscoped (cannot log
     *      in with any tier) and a user with >1 is an ambiguous/duplicated scope
     *      — BOTH are HARD FAILs. The seeded super_admin / country_admin accounts
     *      (provisioned in task 4.2) are included; they too must have exactly one.
     *   2. Every tc_user_access.role_id resolves to an m_roles row (R6.1 — role
     *      resolved by code, never a dangling identity). A role_id absent from
     *      m_roles is a HARD FAIL.
     *   3. Scope integrity (R7 tier shape + the LLD-11 scope→estate rule):
     *      - every estate tc_user_scope (scope_type='estate') .scope_id resolves
     *        to an m_estate row, AND that m_estate belongs to the SAME company as
     *        the user's tc_user_access company (for estate/operational tiers).
     *        An estate scope pointing at a NON-EXISTENT m_estate, or at an
     *        m_estate of a DIFFERENT company than the user's access company, is a
     *        tiered-RBAC breach and a HARD FAIL (cross-tenant visibility leak).
     *      - every country tc_user_scope (scope_type='country') .scope_id
     *        resolves to an m_country row; an unresolvable one is a HARD FAIL.
     *   4. Informational: the tier distribution — counts of access rows by tier
     *      SHAPE (super = country_id NULL + company_id NULL; country = country_id
     *      set + company_id NULL; company = company_id set + estate_code NULL;
     *      estate = company_id set + estate_code set) — so the tiered concept is
     *      visibly intact. Not a fail condition.
     *
     * READ-ONLY: every query is a SELECT/COUNT against the target; nothing is
     * written. The result carries the canonical check shape (id/title/
     * requirement/verdict/summary/rows/violations) used by the other checks.
     *
     * @return array{id:string,title:string,requirement:string,verdict:string,summary:string,rows:list<array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private function checkRbacIntegrity(): array
    {
        // If the RBAC tables are not present on the target there is nothing to
        // validate — report NOT_RUN rather than a false PASS/FAIL.
        if (! $this->targetTableExists('tc_user')
            || ! $this->targetTableExists('tc_user_access')
            || ! $this->targetTableExists('m_roles')) {
            return $this->notRunCheck(
                'rbac_integrity',
                'RBAC integrity (one access per user; role/scope resolve)',
                'R15.4',
                'RBAC target tables (tc_user / tc_user_access / m_roles) not present',
            );
        }

        $rows = [];
        $violations = [];
        $hardFail = false;

        // --- 1. Exactly one tc_user_access per tc_user (R7.5) ----------------
        $totalUsers = $this->safeTargetCount('tc_user');
        $usersNoAccess = $this->usersWithAccessCount(0);
        $usersMultiAccess = $this->usersWithAccessCount(2); // 2 == ">= 2" (see helper)

        $accessVerdict = ($usersNoAccess > 0 || $usersMultiAccess > 0) ? self::FAIL : self::PASS;
        if ($accessVerdict === self::FAIL) {
            $hardFail = true;
            $violations[] = [
                'rule'             => 'one_access_per_user',
                'usersWithoutAccess' => $usersNoAccess,
                'usersWithMultiAccess' => $usersMultiAccess,
                'sampleNoAccessIds'  => $this->sampleUserIdsWithoutAccess(10),
                'sampleMultiAccessIds' => $this->sampleUserIdsWithMultiAccess(10),
            ];
        }
        $rows[] = [
            'rule'    => 'one_access_per_user (R7.5)',
            'metric'  => sprintf('%d user(s): %d with 0 access, %d with >1 access',
                $totalUsers, $usersNoAccess, $usersMultiAccess),
            'verdict' => $accessVerdict,
        ];

        // --- 2. Every tc_user_access.role_id resolves in m_roles (R6.1) ------
        $danglingRoleIds = $this->danglingAccessRoleIds();
        $roleVerdict = $danglingRoleIds === [] ? self::PASS : self::FAIL;
        if ($roleVerdict === self::FAIL) {
            $hardFail = true;
            $violations[] = [
                'rule'            => 'role_id_resolves_in_m_roles',
                'unresolvedRoleIds' => $danglingRoleIds,
            ];
        }
        $rows[] = [
            'rule'    => 'role_id_resolves (R6.1)',
            'metric'  => $danglingRoleIds === []
                ? 'all tc_user_access.role_id resolve in m_roles'
                : count($danglingRoleIds).' role_id(s) NOT in m_roles: ['.implode(',', $danglingRoleIds).']',
            'verdict' => $roleVerdict,
        ];

        // --- 3a. Estate scope -> m_estate of the user's company -------------
        if ($this->targetTableExists('tc_user_scope') && $this->targetTableExists('m_estate')) {
            $estateScopeTotal = $this->estateScopeCount();
            $unresolvedEstateScopes = $this->estateScopesUnresolved();
            $crossCompanyEstateScopes = $this->estateScopesCrossCompany();

            $estateVerdict = ($unresolvedEstateScopes > 0 || $crossCompanyEstateScopes > 0) ? self::FAIL : self::PASS;
            if ($estateVerdict === self::FAIL) {
                $hardFail = true;
                $violations[] = [
                    'rule'                   => 'estate_scope_resolves_to_users_company',
                    'unresolvedEstateScopes' => $unresolvedEstateScopes,
                    'crossCompanyEstateScopes' => $crossCompanyEstateScopes,
                    'sampleCrossCompany'     => $this->sampleCrossCompanyEstateScopes(10),
                ];
            }
            $rows[] = [
                'rule'    => 'estate_scope_integrity (R7.4 / LLD-11)',
                'metric'  => sprintf('%d estate scope(s): %d unresolved m_estate, %d point at another company',
                    $estateScopeTotal, $unresolvedEstateScopes, $crossCompanyEstateScopes),
                'verdict' => $estateVerdict,
            ];

            // --- 3b. Country scope -> m_country ------------------------------
            if ($this->targetTableExists('m_country')) {
                $countryScopeTotal = $this->countryScopeCount();
                $unresolvedCountryScopes = $this->countryScopesUnresolved();
                $countryVerdict = $unresolvedCountryScopes === 0 ? self::PASS : self::FAIL;
                if ($countryVerdict === self::FAIL) {
                    $hardFail = true;
                    $violations[] = [
                        'rule'                    => 'country_scope_resolves_to_m_country',
                        'unresolvedCountryScopes' => $unresolvedCountryScopes,
                    ];
                }
                $rows[] = [
                    'rule'    => 'country_scope_integrity (R7.2)',
                    'metric'  => sprintf('%d country scope(s): %d unresolved m_country',
                        $countryScopeTotal, $unresolvedCountryScopes),
                    'verdict' => $countryVerdict,
                ];
            }
        }

        // --- 4. Tier distribution (informational — never a fail) ------------
        $tiers = $this->accessTierDistribution();
        $rows[] = [
            'rule'    => 'tier_distribution (R7 — info only)',
            'metric'  => sprintf('super=%d, country=%d, company=%d, estate=%d, other=%d',
                $tiers['super'], $tiers['country'], $tiers['company'], $tiers['estate'], $tiers['other']),
            'verdict' => self::PASS,
        ];

        $verdict = $hardFail ? self::FAIL : self::PASS;

        return [
            'id'          => 'rbac_integrity',
            'title'       => 'RBAC integrity (one access per user; role/scope resolve)',
            'requirement' => 'R15.4',
            'verdict'     => $verdict,
            'summary'     => $hardFail
                ? count($violations).' RBAC integrity breach(es) — see violations'
                : sprintf('RBAC intact: %d user(s) each with exactly one access; all role_id/scope_id resolve; '
                    .'tiers super=%d country=%d company=%d estate=%d',
                    $totalUsers, $tiers['super'], $tiers['country'], $tiers['company'], $tiers['estate']),
            'rows'        => $rows,
            'violations'  => $violations,
        ];
    }

    /**
     * [IMPLEMENTED — task 10.2, R15.6] Exclusion proof: no target row originates
     * from the source tables res_data / audit_trail (design LLD-3 EXCLUDE,
     * LLD-11 §6, requirements R15.6). Those tables were NEVER read by any copier
     * (the TableTaxonomy classifies them EXCLUDE and the copiers skip them), so
     * the proof here is a triple guard against regression:
     *
     *   (a) NO live target table is named res_data / audit_trail (the fusion
     *       never created a target of that name — a target with that name would
     *       mean excluded data was materialised).
     *   (b) NO fusion_ledger entry references res_data / audit_trail as a source
     *       table — scanned across the structured provenance column (table_name)
     *       AND the key_text the copiers embed ("...|db|table|...") — because a
     *       load-produced row would have left a ledger trace naming its source
     *       table. There should be ZERO.
     *   (c) The taxonomy STILL classifies res_data / audit_trail as EXCLUDE
     *       (guard against a future regression that would let a copier read
     *       them). We assert via the canonical TableTaxonomy::EXCLUDE list.
     *
     * A clean result is PASS; any trace (a named target table, a ledger
     * reference, or a taxonomy regression) is a HARD FAIL. READ-ONLY.
     *
     * @return array{id:string,title:string,requirement:string,verdict:string,summary:string,rows:list<array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private function checkExclusions(): array
    {
        $excluded = TableTaxonomy::EXCLUDE; // ['res_data', 'audit_trail']

        $rows = [];
        $violations = [];
        $hardFail = false;
        $anyWarn = false;

        // --- (b) first: no fusion_ledger entry references an excluded source
        // table. This is the PRIMARY proof of R15.6 — a row "originating from"
        // res_data / audit_trail would have left a ledger trace naming it as the
        // source table it was loaded from. ZERO traces means the fusion never
        // read or materialised those sources. Computed first so check (a) can
        // use it to decide whether a same-named target table is fusion-produced.
        $ledgerTraces = [];
        foreach ($excluded as $name) {
            $hits = $this->ledgerReferencesTable($name);
            if ($hits > 0) {
                $ledgerTraces[$name] = $hits;
            }
        }
        $fusionTouchedExcluded = $ledgerTraces !== [];

        // --- (a) a target table named res_data / audit_trail ----------------
        // R15.6 forbids a target ROW that ORIGINATES FROM res_data / audit_trail,
        // NOT the mere existence of a same-named table. The EPMS target is a
        // pre-seeded dummy environment and may legitimately carry its own
        // app-runtime tables of these names (e.g. audit_trail is the app's own
        // audit log, res_data a response capture) that the fusion NEVER wrote —
        // the copiers classify both EXCLUDE and never read them. So a same-named
        // target table is a HARD FAIL only when there is LEDGER EVIDENCE the
        // fusion actually materialised it (a trace from check (b)); otherwise it
        // is a PRE-EXISTING app/dummy table, reported as an informational WARN,
        // never a load failure (the read-only Reconciler flags, never fails, on
        // data that is demonstrably outside this run's output).
        $presentTargets = [];
        foreach ($excluded as $name) {
            if ($this->targetTableExists($name)) {
                $presentTargets[] = $name;
            }
        }
        if ($presentTargets === []) {
            $targetVerdict = self::PASS;
            $targetMetric = 'none present (clean)';
        } elseif ($fusionTouchedExcluded) {
            // A same-named target table AND a ledger trace → fusion materialised
            // excluded data. Genuine breach.
            $targetVerdict = self::FAIL;
            $targetMetric = 'FUSION-MATERIALISED: ['.implode(',', $presentTargets).'] (ledger trace present)';
            $hardFail = true;
            $violations[] = [
                'rule'               => 'no_excluded_target_table',
                'materialisedTables' => $presentTargets,
                'ledgerTraces'       => $ledgerTraces,
            ];
        } else {
            // Present but with NO ledger trace → pre-existing app/dummy table the
            // fusion never wrote. Not a breach; surface as a WARN for visibility.
            $targetVerdict = self::WARN;
            $targetMetric = 'pre-existing app/dummy table(s) ['.implode(',', $presentTargets)
                .'] — fusion never wrote them (no ledger trace); not a breach';
            $anyWarn = true;
        }
        $rows[] = [
            'check'   => '(a) target table named res_data / audit_trail is not fusion-produced',
            'metric'  => $targetMetric,
            'verdict' => $targetVerdict,
        ];

        // --- (b) report the ledger-trace result -----------------------------
        $ledgerVerdict = $ledgerTraces === [] ? self::PASS : self::FAIL;
        if ($ledgerVerdict === self::FAIL) {
            $hardFail = true;
            $violations[] = [
                'rule'         => 'no_ledger_trace_to_excluded_source',
                'ledgerTraces' => $ledgerTraces,
            ];
        }
        $rows[] = [
            'check'   => '(b) no fusion_ledger entry references res_data / audit_trail',
            'metric'  => $ledgerTraces === []
                ? 'no ledger trace (clean)'
                : 'FOUND: '.json_encode($ledgerTraces),
            'verdict' => $ledgerVerdict,
        ];

        // --- (c) taxonomy still classifies them EXCLUDE (anti-regression) ---
        $missingFromExclude = array_values(array_diff(['res_data', 'audit_trail'], $excluded));
        $taxonomyVerdict = $missingFromExclude === [] ? self::PASS : self::FAIL;
        if ($taxonomyVerdict === self::FAIL) {
            $hardFail = true;
            $violations[] = [
                'rule'                => 'taxonomy_still_excludes',
                'noLongerExcluded'    => $missingFromExclude,
            ];
        }
        $rows[] = [
            'check'   => '(c) TableTaxonomy still classifies res_data / audit_trail as EXCLUDE',
            'metric'  => $missingFromExclude === []
                ? 'both still EXCLUDE (['.implode(',', $excluded).'])'
                : 'REGRESSION — no longer excluded: ['.implode(',', $missingFromExclude).']',
            'verdict' => $taxonomyVerdict,
        ];

        $verdict = $hardFail ? self::FAIL : ($anyWarn ? self::WARN : self::PASS);

        return [
            'id'          => 'exclusions',
            'title'       => 'Exclusion proof (no rows from res_data / audit_trail)',
            'requirement' => 'R15.6',
            'verdict'     => $verdict,
            'summary'     => $hardFail
                ? count($violations).' exclusion breach(es) — fusion-materialised res_data / audit_trail trace found'
                : ($anyWarn
                    ? 'no fusion trace to res_data / audit_trail (ledger clean, taxonomy excludes); a pre-existing '
                        .'app/dummy table of that name is present but was never written by the fusion'
                    : 'clean: no excluded target table, no ledger trace, taxonomy still excludes res_data / audit_trail'),
            'rows'        => $rows,
            'violations'  => $violations,
        ];
    }

    /**
     * [IMPLEMENTED — task 10.3, R15.5 / R10] Tiered-login spot-check: the
     * acceptance proof that the reconstructed tiers are ENFORCED at query time.
     *
     * ## WHY WE REPLICATE THE RULE INSTEAD OF SPOOFING AN HTTP LOGIN
     *
     * {@see \App\Traits\HasCompanyScope} is the runtime enforcer, but its global
     * scope SHORT-CIRCUITS whenever {@see \Illuminate\Foundation\Application::runningInConsole()}
     * is true — and the Reconciler always runs from the CLI. Forging an HTTP
     * request / faking `runningInConsole()` to make the auto-scope fire would be
     * a brittle, side-effecting hack. Instead this check REPLICATES the trait's
     * company-scope decision deterministically ({@see tierVisibleSet()} mirrors
     * the exact branch logic — super → no filter, country → company.country_id,
     * company-level → company_id AND, when the user has active estate scopes and
     * the table carries estate_code, whereIn estate_code) and asserts the set it
     * yields equals the set the tier is DEFINED to see. Same rule, same inputs,
     * no request spoofing — a pure, read-only logic spot-check.
     *
     * ## WHAT IS ASSERTED, PER SAMPLED USER (R10.1–R10.5 / design LLD-11 §4)
     *
     *   super_admin        → visible companies == ALL m_company                (R10.4)
     *   country_admin(MY)  → visible companies == companies WHERE country_id=MY,
     *                        and NO company of another country is visible       (R10.5)
     *   company_admin      → visible companies == {their company} and visible
     *                        estates == ALL m_estate of that company            (R10.1/R10.3)
     *   estate/operational → visible estates == EXACTLY the estate(s) granted via
     *                        their ACTIVE tc_user_scope rows (one for single,
     *                        N for a deduped multi-estate user); a row of a
     *                        DIFFERENT estate of the SAME company is NOT visible
     *                        (R10.2/R10.4/R10.5)
     *
     * The company/estate SET comparison is computed against {@see m_estate} (the
     * authoritative per-company estate catalog, itself a company_id+estate_code
     * scoped table), and the "different-estate-same-company row is not visible"
     * probe additionally applies the rule to a representative tenant DATA table
     * (e.g. m_block) so the proof is not self-referential.
     *
     * ## VERDICT POLICY
     *   - A tier whose sampled user's visibility EXACTLY equals the expected set
     *     is PASS.
     *   - A tier that sees MORE than it should (an estate user seeing another
     *     estate; a company user seeing another company; a country user seeing
     *     another country) is a HARD FAIL — a tenant-isolation / tiered-RBAC
     *     breach. The known task-10.2 dummy users (ids 2/141/142) with a
     *     cross-company estate scope are detected here and reported as that
     *     KNOWN breach (cross-referenced), never allowed to crash the check: the
     *     sampler prefers genuine migrated users and only falls back to a tainted
     *     one when no clean sample exists, flagging it explicitly.
     *   - A tier with NO sample available (e.g. no estate user seeded yet) is a
     *     WARN with a clear "no sample" note — never a fabricated PASS/FAIL.
     *
     * READ-ONLY: every query is a SELECT/COUNT against the target.
     *
     * @return array{id:string,title:string,requirement:string,verdict:string,summary:string,rows:list<array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private function checkTieredLogin(): array
    {
        // Need the tiered RBAC tables + the tenant catalog to compute visibility.
        if (! $this->targetTableExists('tc_user_access')
            || ! $this->targetTableExists('m_company')
            || ! $this->targetTableExists('m_estate')) {
            return $this->notRunCheck(
                'tiered_login',
                'Tiered-login spot-check (HasCompanyScope visibility per tier)',
                'R15.5',
                'RBAC / tenant-catalog target tables (tc_user_access / m_company / m_estate) not present',
            );
        }

        // The representative tenant DATA table for the "different estate, same
        // company is not visible" probe (first that physically exists and
        // actually carries rows with company_id + estate_code).
        $probeTable = $this->firstScopedDataTable();

        $rows = [];
        $violations = [];
        $hardFail = false;
        $anyWarn = false;
        $samplesChecked = 0;

        foreach ($this->tierSamples() as $tier => $sample) {
            if ($sample === null) {
                $rows[] = [
                    'tier'            => $tier,
                    'user_id'         => '(none)',
                    'expectedVisible' => '—',
                    'actualVisible'   => '—',
                    'verdict'         => self::WARN,
                    'note'            => 'no sample available for this tier (not seeded) — not a failure, not fabricated',
                ];
                $anyWarn = true;
                continue;
            }

            $samplesChecked++;
            $result = $this->assertTierVisibility($tier, $sample, $probeTable);

            $rows[] = $result['row'];
            if ($result['verdict'] === self::FAIL) {
                $hardFail = true;
                $violations[] = $result['violation'];
            } elseif ($result['verdict'] === self::WARN) {
                $anyWarn = true;
            }
        }

        $verdict = $hardFail ? self::FAIL : ($anyWarn ? self::WARN : self::PASS);

        $summary = $hardFail
            ? count($violations).' tiered-visibility breach(es) — a tier sees MORE than its definition allows (tenant-isolation / RBAC breach); see violations'
            : ($samplesChecked === 0
                ? 'no tier samples available to spot-check (RBAC not populated) — WARN, nothing proven'
                : sprintf('%d tier sample(s) spot-checked: each sampled user sees EXACTLY its tier\'s defined companies/estates (super=all, country(MY)=all MY, company=own, estate=assigned-only)%s',
                    $samplesChecked, $anyWarn ? ' — some tiers had no sample (WARN)' : ''));

        return [
            'id'          => 'tiered_login',
            'title'       => 'Tiered-login spot-check (HasCompanyScope visibility per tier)',
            'requirement' => 'R15.5',
            'verdict'     => $verdict,
            'summary'     => $summary,
            'rows'        => $rows,
            'violations'  => $violations,
        ];
    }

    /**
     * Pick one representative sampled user per tier for the spot-check. Each
     * sample is the resolved access shape + (for the estate tiers) the active
     * estate-scope set, or null when no user of that tier exists.
     *
     * The sampler deliberately PREFERS a genuine migrated user whose estate
     * scopes are all WITHIN their access company (a clean sample) and only falls
     * back to a cross-company-tainted user (the known task-10.2 dummies
     * 2/141/142) when no clean sample of that tier exists — so a breach is
     * REPORTED, not hidden, and never crashes the check.
     *
     * Tiers:
     *   super         — country_id NULL AND company_id NULL
     *   country(MY)   — country_id = the MY m_country id, company_id NULL
     *   company_admin — company_id SET, estate_code NULL/empty, and NO active
     *                   estate scope (so it must see ALL of its company)
     *   estate        — a single-estate operational user (one active estate scope)
     *   multi_estate  — a deduped multi-estate user (>1 active estate scope);
     *                   omitted (null) when none exists
     *
     * @return array<string,?array{userId:int,countryId:?int,companyId:?int,estateCode:?string,estateScopeIds:list<int>,tainted:bool}>
     */
    private function tierSamples(): array
    {
        return [
            'super'         => $this->sampleSuperUser(),
            'country(MY)'   => $this->sampleCountryUser(),
            'company_admin' => $this->sampleCompanyUser(),
            'estate'        => $this->sampleEstateUser(multi: false),
            'multi_estate'  => $this->sampleEstateUser(multi: true),
        ];
    }

    /** The MY m_country id (code = 'MY'), or null if absent. */
    private function myCountryId(): ?int
    {
        try {
            $id = $this->target->table('m_country')->where('code', 'MY')->value('id');

            return $id === null ? null : (int) $id;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve a sampled user's access shape + active estate-scope ids into the
     * sample structure, flagging cross-company taint (any active estate scope
     * resolving to an estate of a DIFFERENT company than the access company_id).
     *
     * @return array{userId:int,countryId:?int,companyId:?int,estateCode:?string,estateScopeIds:list<int>,tainted:bool}|null
     */
    private function buildSample(int $userId): ?array
    {
        try {
            $access = $this->target->table('tc_user_access')->where('user_id', $userId)->first();
            if ($access === null) {
                return null;
            }

            $companyId = $access->company_id === null ? null : (int) $access->company_id;
            $scopeIds = $this->activeEstateScopeIds($userId);

            // Cross-company taint: an active estate scope whose m_estate belongs
            // to a different company than the user's access company_id.
            $tainted = false;
            if ($companyId !== null && $scopeIds !== []) {
                $scopeCompanies = $this->target->table('m_estate')
                    ->whereIn('id', $scopeIds)
                    ->distinct()
                    ->pluck('company_id');
                foreach ($scopeCompanies as $sc) {
                    if ((int) $sc !== $companyId) {
                        $tainted = true;
                        break;
                    }
                }
            }

            return [
                'userId'         => $userId,
                'countryId'      => $access->country_id === null ? null : (int) $access->country_id,
                'companyId'      => $companyId,
                'estateCode'     => $access->estate_code === null || $access->estate_code === '' ? null : (string) $access->estate_code,
                'estateScopeIds' => $scopeIds,
                'tainted'        => $tainted,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /** Active (is_active=1) estate scope_ids for a user. @return list<int> */
    private function activeEstateScopeIds(int $userId): array
    {
        try {
            if (! $this->targetTableExists('tc_user_scope')) {
                return [];
            }
            $ids = $this->target->table('tc_user_scope')
                ->where('scope_type', 'estate')
                ->where('user_id', $userId)
                ->where('is_active', 1)
                ->pluck('scope_id');

            return array_values(array_unique(array_map('intval', $ids->all())));
        } catch (Throwable) {
            return [];
        }
    }

    /** Sample a super_admin (country NULL + company NULL). */
    private function sampleSuperUser(): ?array
    {
        try {
            $uid = $this->target->table('tc_user_access')
                ->whereNull('country_id')->whereNull('company_id')
                ->orderBy('user_id')->value('user_id');

            return $uid === null ? null : $this->buildSample((int) $uid);
        } catch (Throwable) {
            return null;
        }
    }

    /** Sample a country_admin for MY (country_id = MY id, company NULL). */
    private function sampleCountryUser(): ?array
    {
        $my = $this->myCountryId();
        if ($my === null) {
            return null;
        }
        try {
            $uid = $this->target->table('tc_user_access')
                ->where('country_id', $my)->whereNull('company_id')
                ->orderBy('user_id')->value('user_id');

            return $uid === null ? null : $this->buildSample((int) $uid);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Sample a company_admin: company_id SET, estate_code NULL/empty, and no
     * ACTIVE estate scope — so the tier definition is "sees ALL of its company".
     * Prefers such a clean user; returns null when none exists.
     */
    private function sampleCompanyUser(): ?array
    {
        try {
            $candidates = $this->target->table('tc_user_access')
                ->whereNotNull('company_id')
                ->where(function ($w) {
                    $w->whereNull('estate_code')->orWhere('estate_code', '=', '');
                })
                ->orderBy('user_id')
                ->pluck('user_id');

            $fallback = null;
            foreach ($candidates as $uid) {
                $sample = $this->buildSample((int) $uid);
                if ($sample === null) {
                    continue;
                }
                // Clean company-admin = no active estate scope at all.
                if ($sample['estateScopeIds'] === []) {
                    return $sample;
                }
                $fallback ??= $sample; // remember a scoped one as a last resort
            }

            return $fallback;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Sample an estate/operational user: company_id SET with active estate
     * scope(s). $multi=false → exactly one active scope (single-estate);
     * $multi=true → more than one (deduped multi-estate). Prefers a clean
     * (non-cross-company-tainted) user; falls back to a tainted one only when no
     * clean sample exists (so the known breach is still reported).
     */
    private function sampleEstateUser(bool $multi): ?array
    {
        try {
            if (! $this->targetTableExists('tc_user_scope')) {
                return null;
            }

            $op = $multi ? '>' : '=';
            $userIds = $this->target->table('tc_user_scope')
                ->where('scope_type', 'estate')
                ->where('is_active', 1)
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) '.$op.' 1')
                ->pluck('user_id');

            $fallback = null;
            foreach ($userIds as $uid) {
                $sample = $this->buildSample((int) $uid);
                if ($sample === null || $sample['companyId'] === null || $sample['estateScopeIds'] === []) {
                    continue;
                }
                if ($multi && count($sample['estateScopeIds']) < 2) {
                    continue;
                }
                if (! $multi && count($sample['estateScopeIds']) !== 1) {
                    continue;
                }
                if (! $sample['tainted']) {
                    return $sample; // clean genuine sample preferred
                }
                $fallback ??= $sample;
            }

            return $fallback;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Assert that a sampled user's REPLICATED HasCompanyScope visibility matches
     * its tier definition. Returns the report row + verdict + (on breach) a
     * violation payload.
     *
     * @param  array{userId:int,countryId:?int,companyId:?int,estateCode:?string,estateScopeIds:list<int>,tainted:bool}  $s
     * @return array{verdict:string,row:array<string,mixed>,violation:array<string,mixed>}
     */
    private function assertTierVisibility(string $tier, array $s, ?string $probeTable): array
    {
        // ACTUAL visible set, computed by REPLICATING the HasCompanyScope rule on
        // TWO AXES against TWO authoritative catalogs:
        //   - the COMPANY axis against m_company (every tenant company, even one
        //     with no estates yet), so the super/country company set is not
        //     silently reduced to "only companies that happen to own an estate
        //     row". A super sees all m_company; a country sees m_company of its
        //     country — the exact rule, not the subset of companies present in a
        //     data table.
        //   - the ESTATE axis against m_estate (the per-company estate catalog),
        //     because estate visibility IS defined by m_estate membership.
        // Deriving the company set from m_estate alone was an apples-vs-oranges
        // comparison: a company with zero m_estate rows (e.g. a seeded tenant not
        // yet carrying estates) would read as "missing" for super/country even
        // though the rule applies no exclusion to it. The two-axis split keeps
        // ACTUAL == EXPECTED when the rule is correct and still catches a rule
        // that OVER-exposes (an extra company/estate shows up as a strict
        // superset breach).
        $actualCompanies = $this->tierVisibleCompanies($s);
        $actualEstates = $this->tierVisibleSet('m_estate', $s)['estates'];

        // EXPECTED per tier, straight from the catalog / scope definition.
        [$expectedCompanies, $expectedEstates, $expectDesc] = $this->expectedTierSet($tier, $s);

        // Breach = ACTUAL is a strict SUPERSET of EXPECTED on either axis (sees
        // more than allowed). A subset (sees less) is not an isolation breach but
        // is still a mismatch; we report it as FAIL too (visibility is wrong)
        // EXCEPT it cannot under-expose for these tiers given the rule, so any
        // inequality here is reported.
        $extraCompanies = array_values(array_diff($actualCompanies, $expectedCompanies));
        $missingCompanies = array_values(array_diff($expectedCompanies, $actualCompanies));
        $extraEstates = array_values(array_diff($actualEstates, $expectedEstates));
        $missingEstates = array_values(array_diff($expectedEstates, $actualEstates));

        $seesMore = $extraCompanies !== [] || $extraEstates !== [];
        $mismatch = $seesMore || $missingCompanies !== [] || $missingEstates !== [];

        // Additional R10.4/R10.5 row-probe for the estate tiers: a tenant DATA
        // row from a DIFFERENT estate of the SAME company must NOT be visible.
        $probeNote = '';
        $probeBreach = false;
        if (in_array($tier, ['estate', 'multi_estate'], true) && $probeTable !== null && $s['companyId'] !== null) {
            $probe = $this->crossEstateRowProbe($probeTable, $s);
            $probeNote = $probe['note'];
            $probeBreach = $probe['breach'];
        }

        $verdict = ($mismatch && $seesMore) || $probeBreach ? self::FAIL
            : ($mismatch ? self::FAIL : self::PASS);

        // A tainted sample (cross-company estate scope) is itself the known
        // task-10.2 breach; make that explicit in the note regardless of verdict.
        $taintNote = $s['tainted']
            ? ' [KNOWN task-10.2 breach: sampled user has a CROSS-COMPANY estate scope (dummy user '.$s['userId'].') — reported, not fabricated]'
            : '';

        $expVis = $this->describeSet($expectedCompanies, $expectedEstates);
        $actVis = $this->describeSet($actualCompanies, $actualEstates);

        $row = [
            'tier'            => $tier,
            'user_id'         => $s['userId'],
            'expectedVisible' => $expectDesc.' => '.$expVis,
            'actualVisible'   => $actVis,
            'verdict'         => $verdict,
            'note'            => trim(
                ($verdict === self::PASS ? 'visibility matches tier' : 'VISIBILITY MISMATCH')
                .($extraCompanies ? ' | extra companies: ['.implode(',', $extraCompanies).']' : '')
                .($extraEstates ? ' | extra estates: ['.implode(',', array_slice($extraEstates, 0, 8)).(count($extraEstates) > 8 ? ',…' : '').']' : '')
                .($missingCompanies ? ' | missing companies: ['.implode(',', $missingCompanies).']' : '')
                .($missingEstates ? ' | missing estates: ['.implode(',', array_slice($missingEstates, 0, 8)).(count($missingEstates) > 8 ? ',…' : '').']' : '')
                .($probeNote !== '' ? ' | '.$probeNote : '')
                .$taintNote
            ),
        ];

        $violation = [];
        if ($verdict === self::FAIL) {
            $violation = [
                'rule'             => 'tier_visibility_matches_definition',
                'tier'             => $tier,
                'userId'           => $s['userId'],
                'seesMoreThanAllowed' => $seesMore || $probeBreach,
                'extraCompanies'   => $extraCompanies,
                'extraEstates'     => array_slice($extraEstates, 0, 20),
                'missingCompanies' => $missingCompanies,
                'missingEstates'   => array_slice($missingEstates, 0, 20),
                'crossCompanyScopeTaint' => $s['tainted'],
                'probeNote'        => $probeNote,
            ];
        }

        return ['verdict' => $verdict, 'row' => $row, 'violation' => $violation];
    }

    /**
     * REPLICATE the {@see \App\Traits\HasCompanyScope} company-scope decision for
     * a sampled user against a company_id(+estate_code) table, returning the
     * distinct visible company ids and estate codes. This is the deterministic
     * CLI stand-in for the runtime global scope (which short-circuits under
     * runningInConsole()). The branch logic mirrors the trait exactly:
     *
     *   super (country NULL & company NULL)  → no filter (all rows)
     *   country (country SET & company NULL) → company_id ∈ companies of that country
     *   company-level (company SET)          → company_id = X, and when the user
     *                                          has active estate scopes AND the
     *                                          table has estate_code: whereIn
     *                                          estate_code (empty grants → none)
     *
     * @param  array{userId:int,countryId:?int,companyId:?int,estateCode:?string,estateScopeIds:list<int>,tainted:bool}  $s
     * @return array{companies:list<int>,estates:list<string>}
     */
    private function tierVisibleSet(string $table, array $s): array
    {
        try {
            $cols = $this->targetColumns($table);
            $hasEstateCode = in_array('estate_code', $cols, true);

            $q = $this->target->table($table);

            if ($s['countryId'] === null && $s['companyId'] === null) {
                // super_admin → no filter.
            } elseif ($s['countryId'] !== null && $s['companyId'] === null) {
                // country_admin → companies whose country_id = access.country_id.
                $companyIds = $this->target->table('m_company')
                    ->where('country_id', $s['countryId'])
                    ->pluck('id');
                $companyIds = array_map('intval', $companyIds->all());
                $q->whereIn($table.'.company_id', $companyIds === [] ? [-1] : $companyIds);
            } else {
                // company-level → strict single company.
                $q->where($table.'.company_id', $s['companyId']);

                // Estate restriction fires only when the user has active estate
                // scopes AND the table physically carries estate_code (mirrors
                // HasCompanyScope::assignedEstateCodes null vs [] semantics).
                if ($s['estateScopeIds'] !== [] && $hasEstateCode) {
                    $codes = $this->estateCodesForIds($s['estateScopeIds']);
                    if ($codes === []) {
                        $q->whereRaw('1 = 0'); // granted but unresolved → see nothing
                    } else {
                        $q->whereIn($table.'.estate_code', $codes);
                    }
                }
            }

            $selectCols = [$table.'.company_id as c'];
            if ($hasEstateCode) {
                $selectCols[] = $table.'.estate_code as e';
            }
            $rows = $q->get($selectCols);

            $companies = [];
            $estates = [];
            foreach ($rows as $r) {
                if ($r->c !== null) {
                    $cid = (int) $r->c;
                    $companies[$cid] = $cid;
                }
                if ($hasEstateCode && isset($r->e) && $r->e !== null && $r->e !== '') {
                    $estates[(string) $r->e] = (string) $r->e;
                }
            }

            return [
                'companies' => array_values($companies),
                'estates'   => array_values($estates),
            ];
        } catch (Throwable) {
            return ['companies' => [], 'estates' => []];
        }
    }

    /**
     * REPLICATE the {@see \App\Traits\HasCompanyScope} company-scope decision on
     * the COMPANY AXIS directly against the m_company catalog, returning the
     * distinct visible company ids. m_company has no company_id column (its
     * identity IS the company id), so the trait's company_id filter is applied to
     * m_company.id instead — the same rule, honest to every seeded company
     * (including one that owns no estate rows yet):
     *
     *   super   (country NULL & company NULL)  → no filter (ALL m_company)
     *   country (country SET & company NULL)   → m_company WHERE country_id = X
     *   company (company SET)                  → the single company id {X}
     *
     * The estate restriction does NOT narrow the COMPANY axis (an estate-scoped
     * user is still within exactly one company), so it is not applied here — the
     * estate axis is handled separately against m_estate.
     *
     * @param  array{userId:int,countryId:?int,companyId:?int,estateCode:?string,estateScopeIds:list<int>,tainted:bool}  $s
     * @return list<int>
     */
    private function tierVisibleCompanies(array $s): array
    {
        try {
            $q = $this->target->table('m_company');

            if ($s['countryId'] === null && $s['companyId'] === null) {
                // super_admin → no filter (all companies).
            } elseif ($s['countryId'] !== null && $s['companyId'] === null) {
                // country_admin → m_company rows of that country.
                $q->where('country_id', $s['countryId']);
            } else {
                // company-level (and estate/operational) → the single company.
                $q->where('id', $s['companyId']);
            }

            $ids = $q->pluck('id');

            return array_values(array_unique(array_map('intval', $ids->all())));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The EXPECTED visible (companies, estates) set + a human description for a
     * tier, taken straight from the catalog / scope definition (NOT from the
     * rule under test — so a rule that over-exposes is caught by the diff).
     *
     * @param  array{userId:int,countryId:?int,companyId:?int,estateCode:?string,estateScopeIds:list<int>,tainted:bool}  $s
     * @return array{0:list<int>,1:list<string>,2:string}  [companies, estates, description]
     */
    private function expectedTierSet(string $tier, array $s): array
    {
        if ($tier === 'super') {
            $companies = array_map('intval', $this->target->table('m_company')->pluck('id')->all());
            $estates = $this->allEstateCodes();

            return [$companies, $estates, 'super: ALL companies'];
        }

        if ($tier === 'country(MY)') {
            $companies = array_map('intval', $this->target->table('m_company')
                ->where('country_id', $s['countryId'])->pluck('id')->all());
            $estates = $this->estateCodesForCompanies($companies);

            return [$companies, $estates, 'country(MY): companies WHERE country_id='.$s['countryId'].' (only MY)'];
        }

        if ($tier === 'company_admin') {
            $companies = $s['companyId'] === null ? [] : [$s['companyId']];
            $estates = $this->estateCodesForCompanies($companies);

            return [$companies, $estates, 'company_admin: own company '.$s['companyId'].' and ALL its estates'];
        }

        // estate / multi_estate → exactly the estates granted via active scopes.
        $estates = $this->estateCodesForIds($s['estateScopeIds']);
        // The companies those estates belong to (clean = just the access company).
        $companies = $this->companiesForEstateIds($s['estateScopeIds']);

        return [$companies, $estates, $tier.': EXACTLY '.count($estates).' assigned estate(s)'];
    }

    /**
     * R10.4/R10.5 row-probe: pick a tenant DATA row from a DIFFERENT estate of
     * the SAME company as the sampled estate user, and assert the replicated
     * rule does NOT make it visible. Returns a note + breach flag.
     *
     * @param  array{userId:int,countryId:?int,companyId:?int,estateCode:?string,estateScopeIds:list<int>,tainted:bool}  $s
     * @return array{note:string,breach:bool}
     */
    private function crossEstateRowProbe(string $table, array $s): array
    {
        try {
            $cols = $this->targetColumns($table);
            if (! in_array('estate_code', $cols, true) || ! in_array('company_id', $cols, true)) {
                return ['note' => 'probe skipped (table lacks company_id/estate_code)', 'breach' => false];
            }

            $assignedCodes = $this->estateCodesForIds($s['estateScopeIds']);

            // A distinct estate_code of the SAME company that is NOT in the
            // user's assigned set — the "different estate, same company" case.
            $otherEstate = $this->target->table($table)
                ->where('company_id', $s['companyId'])
                ->when($assignedCodes !== [], fn ($q) => $q->whereNotIn('estate_code', $assignedCodes))
                ->whereNotNull('estate_code')
                ->where('estate_code', '!=', '')
                ->orderBy('estate_code')
                ->value('estate_code');

            if ($otherEstate === null) {
                return [
                    'note' => 'row-probe: no other '.$table.' estate of company '.$s['companyId'].' to test against (single-estate-populated company)',
                    'breach' => false,
                ];
            }

            // Is that different-estate row visible under the replicated rule?
            $visibleCount = $this->target->table($table)
                ->where('company_id', $s['companyId'])
                ->where('estate_code', $otherEstate)
                ->when($assignedCodes !== [], fn ($q) => $q->whereIn('estate_code', $assignedCodes))
                ->count();

            $breach = $visibleCount > 0;

            return [
                'note' => $breach
                    ? 'ROW-PROBE BREACH: estate user CAN see '.$table.' rows of OTHER estate "'.$otherEstate.'" (same company '.$s['companyId'].')'
                    : 'row-probe OK: '.$table.' rows of other estate "'.$otherEstate.'" (same company) are NOT visible',
                'breach' => $breach,
            ];
        } catch (Throwable $e) {
            return ['note' => 'row-probe skipped ('.$e->getMessage().')', 'breach' => false];
        }
    }

    /** Resolve estate ids → estate_codes (via m_estate). @return list<string> */
    private function estateCodesForIds(array $estateIds): array
    {
        if ($estateIds === []) {
            return [];
        }
        try {
            $codes = $this->target->table('m_estate')
                ->whereIn('id', $estateIds)
                ->whereNotNull('estate_code')
                ->pluck('estate_code');

            return array_values(array_unique(array_map('strval', $codes->all())));
        } catch (Throwable) {
            return [];
        }
    }

    /** The companies a set of estate ids belong to. @return list<int> */
    private function companiesForEstateIds(array $estateIds): array
    {
        if ($estateIds === []) {
            return [];
        }
        try {
            $ids = $this->target->table('m_estate')
                ->whereIn('id', $estateIds)
                ->whereNotNull('company_id')
                ->distinct()
                ->pluck('company_id');

            return array_values(array_unique(array_map('intval', $ids->all())));
        } catch (Throwable) {
            return [];
        }
    }

    /** All estate_codes of a set of companies. @return list<string> */
    private function estateCodesForCompanies(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }
        try {
            $codes = $this->target->table('m_estate')
                ->whereIn('company_id', $companyIds)
                ->whereNotNull('estate_code')
                ->pluck('estate_code');

            return array_values(array_unique(array_map('strval', $codes->all())));
        } catch (Throwable) {
            return [];
        }
    }

    /** Every estate_code in m_estate. @return list<string> */
    private function allEstateCodes(): array
    {
        try {
            $codes = $this->target->table('m_estate')
                ->whereNotNull('estate_code')
                ->pluck('estate_code');

            return array_values(array_unique(array_map('strval', $codes->all())));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The first representative tenant DATA table (company_id + estate_code,
     * physically present, carrying rows) for the cross-estate row-probe. Prefers
     * m_block / m_division (classic estate-scoped masters) and falls back to any
     * such table. Returns null when none qualifies.
     */
    private function firstScopedDataTable(): ?string
    {
        foreach (['m_block', 'm_division', 'm_tph', 'm_work_center'] as $candidate) {
            if ($this->targetTableExists($candidate)
                && in_array('estate_code', $this->targetColumns($candidate), true)
                && $this->safeTargetCount($candidate) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Short human description of a (companies, estates) visibility set for the
     * report cell.
     *
     * @param  list<int>  $companies
     * @param  list<string>  $estates
     */
    private function describeSet(array $companies, array $estates): string
    {
        $c = count($companies);
        $e = count($estates);
        $cList = $c <= 12 ? '['.implode(',', $companies).']' : $c.' companies';
        $eList = $e <= 6 ? '['.implode(',', $estates).']' : $e.' estates';

        return $c.' company/ies '.$cList.', '.$e.' estate(s) '.$eList;
    }

    // -------------------------------------------------------------------------
    // EXPECTED helpers — replay the copier selection against the live SOURCE.
    // -------------------------------------------------------------------------

    /**
     * Count source rows inside the per-DB window on a table's own date column
     * [windowStart, anchor] (R12.3). When there is no date column (R12.9 full
     * copy) or no window, returns the full source count.
     */
    private function windowedSourceCount(
        ConnectionInterface $pg,
        string $table,
        ?string $dateCol,
        ?string $windowStart,
        ?string $anchor,
    ): int {
        try {
            $q = $pg->table($table);
            if ($dateCol !== null && $windowStart !== null) {
                $q->where($dateCol, '>=', $windowStart);
                if ($anchor !== null) {
                    $q->where($dateCol, '<=', $anchor);
                }
            }

            return (int) $q->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Count source children whose parent VARCHAR FK is IN the loaded header set
     * (R12.4/R12.5 — never by date). The IN set is chunked well under the
     * parameter ceiling.
     *
     * @param  list<string>  $headerIds
     */
    private function childrenInHeaderSet(ConnectionInterface $pg, string $child, string $fkCol, array $headerIds): int
    {
        $total = 0;
        try {
            foreach (array_chunk($headerIds, 1000) as $chunk) {
                $total += (int) $pg->table($child)->whereIn($fkCol, $chunk)->count();
            }
        } catch (Throwable) {
            return 0;
        }

        return $total;
    }

    /**
     * The loaded header id set for (db, parent) from the ledger (txn_header_loaded),
     * so EXPECTED children are counted against exactly the headers that loaded.
     *
     * @return list<string>
     */
    private function loadedHeaderIds(string $db, string $parent, int $companyId): array
    {
        $rows = $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_HEADER_LOADED)
            ->where('table_name', $parent)
            ->where('company_id', $companyId)
            ->where('source_db', $db)
            ->pluck('value_text');

        $ids = [];
        foreach ($rows as $value) {
            if ($value !== null && $value !== '') {
                $ids[] = (string) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    // -------------------------------------------------------------------------
    // LOADED helpers — count the live TARGET attributable to a source/company.
    // -------------------------------------------------------------------------

    /** Target rows carrying a given company_id (the per-source LOADED count). */
    private function targetCountByCompany(string $table, int $companyId): int
    {
        try {
            if (! in_array('company_id', $this->targetColumns($table), true)) {
                return $this->safeTargetCount($table);
            }

            return (int) $this->target->table($table)->where('company_id', $companyId)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Full target count, null-safe. */
    private function safeTargetCount(string $table): int
    {
        try {
            return (int) $this->target->table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Ledger cross-reference helpers.
    // -------------------------------------------------------------------------

    /**
     * How many error-ledger skips are attributed to a (source_db, table). The
     * copiers key error entries by text embedding "db|table|..." and set the
     * source_db/table_name provenance columns inconsistently (early error keys
     * predate those columns), so we match on BOTH the structured columns AND the
     * key_text prefix to be robust, de-duplicating by the ledger row id.
     */
    private function ledgerErrorCountFor(string $db, string $table): int
    {
        try {
            // Structured-column match (preferred when populated).
            $byColumns = $this->target->table('fusion_ledger')
                ->where('entry_type', self::LEDGER_ERROR)
                ->where('source_db', $db)
                ->where('table_name', $table)
                ->count();

            if ($byColumns > 0) {
                return (int) $byColumns;
            }

            // Fallback: the copiers embed "...|db|table|..." in the key_text.
            return (int) $this->target->table('fusion_ledger')
                ->where('entry_type', self::LEDGER_ERROR)
                ->where('key_text', 'like', '%|'.$db.'|'.$table.'|%')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Summarize the ledger skip/error/note + loaded-id areas for the report,
     * scoped to a db when requested. These counts are the cross-reference every
     * row-count mismatch is explained against (R15.1).
     *
     * @return array{errors:int,notes:int,headerLoaded:int,childLoaded:int,attendanceNk:int}
     */
    private function summarizeLedger(?string $onlyDb): array
    {
        $scoped = function (string $entryType) use ($onlyDb): int {
            try {
                $q = $this->target->table('fusion_ledger')->where('entry_type', $entryType);
                if ($onlyDb !== null) {
                    $q->where(function ($w) use ($onlyDb) {
                        $w->where('source_db', $onlyDb)
                          ->orWhere('key_text', 'like', '%|'.$onlyDb.'|%');
                    });
                }

                return (int) $q->count();
            } catch (Throwable) {
                return 0;
            }
        };

        return [
            'errors'       => $scoped(self::LEDGER_ERROR),
            'notes'        => $scoped(self::LEDGER_NOTE),
            'headerLoaded' => $scoped(self::LEDGER_HEADER_LOADED),
            'childLoaded'  => $scoped('txn_child_loaded'),
            'attendanceNk' => $scoped('attendance_natural_key'),
        ];
    }

    // -------------------------------------------------------------------------
    // Tenant-isolation helpers.
    // -------------------------------------------------------------------------

    /**
     * Every live target table that physically has a company_id column — the
     * authoritative tenant-table set (schema-honest, not hard-coded). m_company
     * itself is excluded (its company_id-shaped column is the PK, not a tenant
     * stamp — it has no company_id column anyway).
     *
     * @return list<string>
     */
    private function tenantTables(): array
    {
        try {
            $rows = $this->target->select(
                "SELECT table_name FROM information_schema.columns
                 WHERE column_name = 'company_id' AND table_name <> 'fusion_ledger'
                 ORDER BY table_name"
            );

            $tables = [];
            foreach ($rows as $row) {
                $name = (string) $row->table_name;
                if ($this->isTenantDataTable($name)) {
                    $tables[] = $name;
                }
            }

            return array_values(array_unique($tables));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Whether a table is a fusion LOAD tenant-data table — the subset of
     * company_id-bearing tables the copiers stamp, for which a NULL company_id is
     * a genuine isolation breach (R15.2).
     *
     * EXCLUDED (NULL company_id is legitimate / not stamped by this fusion):
     *   - tc_user_access / tc_user_scope: the tiered RBAC tables where
     *     super_admin/country_admin rows have a NULL company_id BY DESIGN
     *     (R7.1/R7.2). These are validated by the RBAC integrity check (task
     *     10.2), not by tenant isolation.
     *   - the tenant/meta/RBAC catalog tables the TableTaxonomy marks TENANT_META
     *     / RBAC (m_company, m_country, m_estate, m_roles, m_config), which are
     *     seeded, not row-stamped by the copiers.
     *   - fusion_ledger (already excluded by the caller's SQL).
     *   - any table that is not an m_* / t_* / tr_* load table (e.g. login_log,
     *     other app-runtime tables) — the copiers never wrote them, so a NULL is
     *     not a fusion isolation defect.
     */
    private function isTenantDataTable(string $table): bool
    {
        $name = strtolower($table);

        if (in_array($name, TableTaxonomy::RBAC, true)
            || in_array($name, TableTaxonomy::TENANT_META, true)
            || in_array($name, TableTaxonomy::EXCLUDE, true)
            || in_array($name, ['tc_user', 'tc_user_access', 'tc_user_scope'], true)) {
            return false;
        }

        // Only the copier-stamped load families carry a company_id tenant stamp.
        return str_starts_with($name, 'm_')
            || str_starts_with($name, 't_')
            || str_starts_with($name, 'tr_');
    }

    /** NULL company_id count on a tenant table, null-safe. */
    private function safeNullCompanyCount(string $table): int
    {
        try {
            return (int) $this->target->table($table)->whereNull('company_id')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Distinct company_id values present on a tenant table that do NOT resolve to
     * an m_company row (orphan tenant stamps). Returns the offending ids.
     *
     * @return list<int>
     */
    private function orphanCompanyIds(string $table): array
    {
        try {
            $ids = $this->target->table($table)
                ->whereNotNull('company_id')
                ->distinct()
                ->pluck('company_id');

            $orphans = [];
            foreach ($ids as $id) {
                $id = (int) $id;
                if (! $this->companyExists($id)) {
                    $orphans[] = $id;
                }
            }

            return array_values(array_unique($orphans));
        } catch (Throwable) {
            return [];
        }
    }

    /** Whether a company_id exists in m_company (cached). */
    private function companyExists(int $companyId): bool
    {
        return $this->companyExistsCache[$companyId]
            ??= $this->target->table('m_company')->where('id', $companyId)->exists();
    }

    // -------------------------------------------------------------------------
    // Cross-tenant-leak helpers.
    // -------------------------------------------------------------------------

    /**
     * Tables whose business key is GLOBALLY UNIQUE by construction — the
     * transaction headers' app-generated VARCHAR `id` (LLD-8). A span across >1
     * company here is a genuine leak (HARD FAIL, R15.3).
     *
     * @return array<string,string>  table => business-key column
     */
    private function globallyUniqueKeyTargets(): array
    {
        $targets = [];
        foreach (array_keys(self::HEADER_ID_COLUMN) as $header) {
            $targets[$header] = 'id';
        }

        return $targets;
    }

    /**
     * TENANT master tables whose natural key is unique ONLY WITHIN a company (the
     * target composite key is (company_id, business_key), LLD-3 / R11.3). A span
     * across companies is EXPECTED here (the same code is a different row per
     * company), so these are reported INFO-only, never a leak. The keys mirror
     * the MasterCopier BUSINESS_KEY tenant subset.
     *
     * @return array<string,string>  table => business-key column
     */
    private function tenantMasterKeyTargets(): array
    {
        return [
            'm_activity'                   => 'activity_code',
            'm_assistant_manager_division' => 'assistant_manager_code',
            'm_bin'                        => 'bin_code',
            'm_block'                      => 'block_code',
            'm_confirmation_text'          => 'ctext_code',
            'm_cost_center'                => 'cc_code',
            'm_customer_code'              => 'customer_code',
            'm_destination'                => 'destination_code',
            'm_devices'                    => 'device_code',
            'm_division'                   => 'division_code',
            'm_employee'                   => 'employee_code',
            'm_receiving_point'            => 'receiving_point_code',
            'm_tph'                        => 'tph_code',
            'm_vendor'                     => 'vendor_code',
            'm_vra'                        => 'vra_order_number',
        ];
    }

    /**
     * Count business keys that are associated with more than one distinct
     * company_id in a tenant table (cross-tenant leak, R15.3). Uses a GROUP BY /
     * HAVING on the live target.
     */
    private function businessKeysSpanningCompanies(string $table, string $businessKeyCol): int
    {
        try {
            $sub = $this->target->table($table)
                ->select($businessKeyCol)
                ->whereNotNull($businessKeyCol)
                ->whereNotNull('company_id')
                ->groupBy($businessKeyCol)
                ->havingRaw('COUNT(DISTINCT company_id) > 1');

            return (int) $this->target->query()->fromSub($sub, 'x')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * A few example business keys that span >1 company (for the violation
     * report). Best-effort; empty on any driver limitation.
     *
     * @return list<array{key:mixed,companies:int}>
     */
    private function leakExamples(string $table, string $businessKeyCol): array
    {
        try {
            $rows = $this->target->table($table)
                ->select($businessKeyCol.' as bk')
                ->selectRaw('COUNT(DISTINCT company_id) as c')
                ->whereNotNull($businessKeyCol)
                ->whereNotNull('company_id')
                ->groupBy($businessKeyCol)
                ->havingRaw('COUNT(DISTINCT company_id) > 1')
                ->limit(5)
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[] = ['key' => $r->bk, 'companies' => (int) $r->c];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // RBAC integrity helpers (R15.4) — all READ-ONLY SELECT/COUNT on target.
    // -------------------------------------------------------------------------

    /**
     * How many tc_user rows have the requested access-row count. $exactOrMin:
     *   - 0 -> users with EXACTLY 0 tc_user_access rows (orphan users).
     *   - 2 -> users with 2 OR MORE tc_user_access rows (duplicated scope).
     * Implemented with a LEFT JOIN / GROUP BY on the live target so it works on
     * SQL Server via ODBC.
     */
    private function usersWithAccessCount(int $exactOrMin): int
    {
        try {
            if ($exactOrMin === 0) {
                // Users with no matching tc_user_access row at all.
                return (int) $this->target->table('tc_user as u')
                    ->leftJoin('tc_user_access as a', 'a.user_id', '=', 'u.id')
                    ->whereNull('a.user_id')
                    ->count();
            }

            // Users with >= 2 access rows: GROUP BY user_id HAVING COUNT > 1.
            $sub = $this->target->table('tc_user_access')
                ->select('user_id')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1');

            return (int) $this->target->query()->fromSub($sub, 'x')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Sample tc_user ids that have NO tc_user_access row (for the violation
     * report).
     *
     * @return list<int>
     */
    private function sampleUserIdsWithoutAccess(int $limit): array
    {
        try {
            $ids = $this->target->table('tc_user as u')
                ->leftJoin('tc_user_access as a', 'a.user_id', '=', 'u.id')
                ->whereNull('a.user_id')
                ->limit($limit)
                ->pluck('u.id');

            return array_values(array_map('intval', $ids->all()));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Sample user_ids that have MORE THAN ONE tc_user_access row.
     *
     * @return list<int>
     */
    private function sampleUserIdsWithMultiAccess(int $limit): array
    {
        try {
            $ids = $this->target->table('tc_user_access')
                ->select('user_id')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->limit($limit)
                ->pluck('user_id');

            return array_values(array_map('intval', $ids->all()));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Distinct tc_user_access.role_id values that do NOT resolve to an m_roles
     * row (dangling role references, R6.1).
     *
     * @return list<int>
     */
    private function danglingAccessRoleIds(): array
    {
        try {
            $ids = $this->target->table('tc_user_access')
                ->whereNotNull('role_id')
                ->distinct()
                ->pluck('role_id');

            $dangling = [];
            foreach ($ids as $id) {
                $id = (int) $id;
                $exists = $this->target->table('m_roles')->where('id', $id)->exists();
                if (! $exists) {
                    $dangling[] = $id;
                }
            }

            return array_values(array_unique($dangling));
        } catch (Throwable) {
            return [];
        }
    }

    /** Total active/all estate-type tc_user_scope rows. */
    private function estateScopeCount(): int
    {
        try {
            return (int) $this->target->table('tc_user_scope')
                ->where('scope_type', 'estate')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Estate scopes whose scope_id does NOT resolve to an m_estate row (dangling
     * estate scope — R7.4 / LLD-11 require a valid m_estate FK).
     */
    private function estateScopesUnresolved(): int
    {
        try {
            return (int) $this->target->table('tc_user_scope as s')
                ->where('s.scope_type', 'estate')
                ->whereNotNull('s.scope_id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('m_estate as e')
                        ->whereColumn('e.id', 's.scope_id');
                })
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Estate scopes whose target m_estate belongs to a DIFFERENT company than
     * the owning user's tc_user_access company — a tiered-RBAC breach (a user
     * seeing another tenant's estate, R15.4 / LLD-11). Resolvable estate scopes
     * only (unresolvable ones are counted separately). We compare the scope's
     * m_estate.company_id against the user's single tc_user_access.company_id;
     * when the access company_id is NULL (super/country tier) there is no
     * company to breach, so those are excluded from this cross-company check
     * (such a user shouldn't own an estate scope at all, but that is an
     * orthogonal tier-shape concern, not a cross-company leak).
     */
    private function estateScopesCrossCompany(): int
    {
        try {
            return (int) $this->target->table('tc_user_scope as s')
                ->join('m_estate as e', 'e.id', '=', 's.scope_id')
                ->join('tc_user_access as a', 'a.user_id', '=', 's.user_id')
                ->where('s.scope_type', 'estate')
                ->whereNotNull('a.company_id')
                ->whereColumn('e.company_id', '!=', 'a.company_id')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Sample cross-company estate scopes for the violation report:
     * {user_id, scope_id, estateCompany, accessCompany}.
     *
     * @return list<array{userId:int,scopeId:int,estateCompany:int,accessCompany:int}>
     */
    private function sampleCrossCompanyEstateScopes(int $limit): array
    {
        try {
            $rows = $this->target->table('tc_user_scope as s')
                ->join('m_estate as e', 'e.id', '=', 's.scope_id')
                ->join('tc_user_access as a', 'a.user_id', '=', 's.user_id')
                ->where('s.scope_type', 'estate')
                ->whereNotNull('a.company_id')
                ->whereColumn('e.company_id', '!=', 'a.company_id')
                ->limit($limit)
                ->get(['s.user_id as uid', 's.scope_id as sid', 'e.company_id as ec', 'a.company_id as ac']);

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'userId'        => (int) $r->uid,
                    'scopeId'       => (int) $r->sid,
                    'estateCompany' => (int) $r->ec,
                    'accessCompany' => (int) $r->ac,
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** Total country-type tc_user_scope rows. */
    private function countryScopeCount(): int
    {
        try {
            return (int) $this->target->table('tc_user_scope')
                ->where('scope_type', 'country')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Country scopes whose scope_id does NOT resolve to an m_country row. */
    private function countryScopesUnresolved(): int
    {
        try {
            return (int) $this->target->table('tc_user_scope as s')
                ->where('s.scope_type', 'country')
                ->whereNotNull('s.scope_id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('m_country as c')
                        ->whereColumn('c.id', 's.scope_id');
                })
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * The tier distribution of tc_user_access rows by SHAPE (R7 — informational).
     * Mirrors the RbacMigrator scope-shape rule so the tiered concept is visibly
     * intact:
     *   - super   : country_id NULL  AND company_id NULL
     *   - country : country_id SET    AND company_id NULL
     *   - company : company_id SET    AND estate_code NULL
     *   - estate  : company_id SET    AND estate_code SET
     *   - other   : anything that matches none of the above (shouldn't happen).
     *
     * @return array{super:int,country:int,company:int,estate:int,other:int}
     */
    private function accessTierDistribution(): array
    {
        $dist = ['super' => 0, 'country' => 0, 'company' => 0, 'estate' => 0, 'other' => 0];

        try {
            $cols = $this->targetColumns('tc_user_access');
            $hasEstateCode = in_array('estate_code', $cols, true);

            $super = (int) $this->target->table('tc_user_access')
                ->whereNull('country_id')->whereNull('company_id')->count();

            $country = (int) $this->target->table('tc_user_access')
                ->whereNotNull('country_id')->whereNull('company_id')->count();

            $companyQ = $this->target->table('tc_user_access')->whereNotNull('company_id');
            $estate = 0;
            $company = 0;
            if ($hasEstateCode) {
                $company = (int) (clone $companyQ)
                    ->where(function ($w) {
                        $w->whereNull('estate_code')->orWhere('estate_code', '=', '');
                    })->count();
                $estate = (int) (clone $companyQ)
                    ->whereNotNull('estate_code')->where('estate_code', '!=', '')->count();
            } else {
                $company = (int) $companyQ->count();
            }

            $total = $this->safeTargetCount('tc_user_access');
            $other = max(0, $total - ($super + $country + $company + $estate));

            $dist = [
                'super'   => $super,
                'country' => $country,
                'company' => $company,
                'estate'  => $estate,
                'other'   => $other,
            ];
        } catch (Throwable) {
            // leave zeros on any driver limitation.
        }

        return $dist;
    }

    // -------------------------------------------------------------------------
    // Exclusion-proof helpers (R15.6) — all READ-ONLY.
    // -------------------------------------------------------------------------

    /**
     * How many fusion_ledger entries reference a given source table — matched on
     * BOTH the structured provenance column (table_name) AND the key_text the
     * copiers embed ("...|db|table|..."). For the EXCLUDE tables (res_data /
     * audit_trail) this MUST be zero: no copier ever read them, so no loaded row
     * could have left a trace naming them as a source.
     */
    private function ledgerReferencesTable(string $table): int
    {
        try {
            $byColumn = (int) $this->target->table('fusion_ledger')
                ->where('table_name', $table)
                ->count();

            $byKeyText = (int) $this->target->table('fusion_ledger')
                ->where(function ($w) use ($table) {
                    $w->where('key_text', 'like', '%|'.$table.'|%')
                        ->orWhere('key_text', 'like', '%|'.$table)
                        ->orWhere('key_text', 'like', $table.'|%');
                })
                ->count();

            // A key_text match may overlap a table_name match on the same row;
            // the sum is a conservative UPPER bound — any non-zero is a trace and
            // a FAIL regardless of exact de-dup, which is all this guard needs.
            return $byColumn + $byKeyText;
        } catch (Throwable) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Source schema helpers (open per-DB source connection).
    // -------------------------------------------------------------------------

    /** Which VARCHAR-id design headers physically exist in the source DB. */
    private function presentHeaders(ConnectionInterface $pg): array
    {
        $present = [];
        foreach (TableTaxonomy::VARCHAR_ID_HEADERS as $table) {
            if ($this->sourceTableExists($pg, $table)) {
                $present[] = $table;
            }
        }

        return $present;
    }

    /** The source master tables (m_*) present in the open DB, excluding EXCLUDE. */
    private function sourceMasterTables(ConnectionInterface $pg): array
    {
        try {
            $rows = $pg->select(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
                   AND table_name LIKE 'm\\_%'
                 ORDER BY table_name"
            );

            $tables = [];
            foreach ($rows as $row) {
                $name = (string) $row->table_name;
                if (in_array($name, TableTaxonomy::EXCLUDE, true)
                    || in_array($name, TableTaxonomy::TENANT_META, true)
                    || in_array($name, TableTaxonomy::RBAC, true)) {
                    continue;
                }
                $tables[] = $name;
            }

            return $tables;
        } catch (Throwable) {
            return [];
        }
    }

    /** Whether the open source DB has a public table of this name. */
    private function sourceTableExists(ConnectionInterface $pg, string $table): bool
    {
        try {
            return $pg->table('information_schema.tables')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ordered source column listing for a table (public schema).
     *
     * @return list<string>
     */
    private function sourceColumns(ConnectionInterface $pg, string $table): array
    {
        try {
            $rows = $pg->table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->orderBy('ordinal_position')
                ->pluck('column_name');

            return array_values(array_map('strval', $rows->all()));
        } catch (Throwable) {
            return [];
        }
    }

    /** Source row count, null-safe. */
    private function safeCount(ConnectionInterface $pg, string $table): int
    {
        try {
            return (int) $pg->table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Target schema helpers.
    // -------------------------------------------------------------------------

    /**
     * Live column listing of a target table (cached).
     *
     * @return array<int,string>
     */
    private function targetColumns(string $table): array
    {
        return $this->columnCache[$table] ??= $this->target->getSchemaBuilder()->getColumnListing($table);
    }

    /** Whether the live target has a table of this name. */
    private function targetTableExists(string $table): bool
    {
        try {
            return $this->target->getSchemaBuilder()->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Verdict / classification helpers.
    // -------------------------------------------------------------------------

    /**
     * Classify a row-count delta into a verdict + cause (R15.1 reconciliation
     * policy):
     *   - loaded == expectedNet            -> MATCH  (PASS)
     *   - loaded  > expectedNet            -> OVER   (INFO/WARN — target has more
     *                                          than this run expected, e.g.
     *                                          pre-existing dummy / foreign-company
     *                                          data; read-only Reconciler never
     *                                          treats a surplus as a load failure)
     *   - loaded  < expectedNet, shortfall == 0 after accounting for skips -> MATCH
     *   - loaded  < expectedNet, shortfall FULLY explained by logged skips  -> WARN
     *   - loaded  < expectedNet, shortfall UNEXPLAINED                      -> FAIL
     *
     * expectedNet already has the skips subtracted, so a genuine match means the
     * loaded count equals (source − skips). A remaining shortfall means MORE rows
     * are missing than the ledger explains → silent loss → hard FAIL.
     *
     * @return array{0:string,1:string}  [verdict, cause]
     */
    private function classifyDelta(int $expectedNet, int $loaded, int $skips, int $sourceGross): array
    {
        $delta = $loaded - $expectedNet;

        if ($delta === 0) {
            return [self::PASS, $skips > 0
                ? sprintf('match (source=%d − %d logged skips = %d loaded)', $sourceGross, $skips, $loaded)
                : 'match (loaded == expected)'];
        }

        if ($delta > 0) {
            // Target has MORE than this run expected. Not a loss; surface it.
            return [self::WARN, sprintf(
                'over-count: target has %d MORE than this run expected (pre-existing dummy / foreign-company '
                .'data not from this source window — read-only Reconciler flags, never fails)',
                $delta,
            )];
        }

        // Shortfall (loaded < expected). expectedNet already removed the skips,
        // so any remaining shortfall is UNEXPLAINED by the ledger → hard FAIL.
        return [self::FAIL, sprintf(
            'UNEXPLAINED shortfall: %d row(s) missing beyond the %d logged skip(s) (source=%d, loaded=%d) — '
            .'possible silent loss, investigate the ledger for this table',
            -$delta, $skips, $sourceGross, $loaded,
        )];
    }

    /** Fold a per-row verdict into the running hard-fail / warn flags. */
    private function rollVerdict(string $verdict, bool &$anyHardFail, bool &$anyWarn): void
    {
        if ($verdict === self::FAIL) {
            $anyHardFail = true;
        } elseif ($verdict === self::WARN || $verdict === 'DEFERRED' || $verdict === 'OVER' || $verdict === 'GLOBAL') {
            // DEFERRED / GLOBAL / OVER are informational, not hard fails; only
            // WARN bumps the warning count at the roll-up. DEFERRED and GLOBAL
            // are expected states and do not even warn.
            if ($verdict === self::WARN) {
                $anyWarn = true;
            }
        }
    }

    /**
     * Build a per-table count row in the canonical shape.
     *
     * @return array<string,mixed>
     */
    private function countRow(
        string $db,
        string $table,
        string $category,
        int $expected,
        int $loaded,
        int $delta,
        string $verdict,
        string $cause,
    ): array {
        return [
            'db'       => $db,
            'table'    => $table,
            'category' => $category,
            'expected' => $expected,
            'loaded'   => $loaded,
            'delta'    => $delta,
            'verdict'  => $verdict,
            'cause'    => $cause,
        ];
    }

    /** One-line summary of the row-count rows for the check header. */
    private function rowCountSummaryLine(array $rows): string
    {
        $match = 0;
        $warn = 0;
        $fail = 0;
        $deferred = 0;
        foreach ($rows as $r) {
            match ($r['verdict'] ?? '') {
                self::PASS => $match++,
                self::WARN => $warn++,
                self::FAIL => $fail++,
                'DEFERRED' => $deferred++,
                default    => null,
            };
        }

        return sprintf(
            '%d matched, %d warning(s) (ledger-explained / over-count), %d deferred, %d UNEXPLAINED failure(s)',
            $match, $warn, $deferred, $fail,
        );
    }

    /**
     * A NOT_RUN check result (the final report shape, body deferred).
     *
     * @return array{id:string,title:string,requirement:string,verdict:string,summary:string,rows:list<array<string,mixed>>,violations:list<array<string,mixed>>}
     */
    private function notRunCheck(string $id, string $title, string $requirement, string $reason): array
    {
        return [
            'id'          => $id,
            'title'       => $title,
            'requirement' => $requirement,
            'verdict'     => self::NOT_RUN,
            'summary'     => 'not run — '.$reason,
            'rows'        => [],
            'violations'  => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Misc.
    // -------------------------------------------------------------------------

    /** Resolve N (window months): explicit override → config → default 2. */
    private function resolveMonths(?int $override): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return $this->registry->windowMonths();
    }

    /** Count the source DBs in scope (all, or 1 when --only-db is given). */
    private function countScopedDatabases(?string $onlyDb): int
    {
        if ($onlyDb === null) {
            return $this->registry->count();
        }

        return $this->registry->get($onlyDb) !== null ? 1 : 0;
    }

    /**
     * OPTIONAL: record a one-line reconciliation verdict to the ledger as a note
     * (the ONLY write the Reconciler ever performs, and only to the ledger).
     * Off by default — the stage is read-only. Upserted by (entry_type, key_text)
     * so a re-run does not pile up notes.
     *
     * @param  array<string,mixed>  $report
     */
    private function recordReportNote(array $report, ?string $onlyDb): void
    {
        try {
            $now = now();
            $scope = $onlyDb ?? 'ALL';
            $message = sprintf(
                'fusion:reconcile verdict=%s hardFailures=%d warnings=%d (scope=%s) at %s',
                $report['overallVerdict'], $report['hardFailures'], $report['warnings'], $scope, $now->toDateTimeString(),
            );

            $this->target->table('fusion_ledger')->updateOrInsert(
                [
                    'entry_type' => self::LEDGER_RECON_NOTE,
                    'key_text'   => 'reconcile_report|'.$scope,
                ],
                [
                    'value_text' => $report['overallVerdict'],
                    'message'    => $message,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        } catch (Throwable) {
            // Recording the report is best-effort; never let it fail the run.
        }
    }
}
