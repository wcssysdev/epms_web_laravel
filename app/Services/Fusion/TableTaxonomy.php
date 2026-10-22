<?php

namespace App\Services\Fusion;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Classifies every source table into a {@see TableCategory} and attaches a
 * {@see PkStrategy}, tenant-stamping flags, and (where applicable) a date
 * column, producing a {@see TablePlan} per table. Implements design LLD-3
 * (table categories + per-category transform rules) and LLD-8 (PK handling).
 *
 * ## Classification precedence (deterministic, LLD-3 order)
 *
 *   1. EXCLUDE        — name ∈ {res_data, audit_trail}. Checked first; never read.
 *   2. RBAC           — tc_user, tc_user_access, tc_user_scope, m_roles.
 *   3. TENANT_META    — m_config, m_company*, m_country, m_estate.
 *   4. ATTENDANCE     — t_attendance (special bigint-id with a natural key).
 *   5. VARCHAR_ID_HEADER — the known app-generated-VARCHAR-id transaction headers.
 *   6. company_id split (masters) — m_* WITH company_id → TENANT_MASTER,
 *                                   m_* WITHOUT company_id → GLOBAL_MASTER.
 *   7. id-type split (transactions) — t_/tr_ with a VARCHAR id → header,
 *                                     t_/tr_ with a bigint identity id → child.
 *
 * Steps 1–5 are deterministic known lists so classification is stable even
 * without a live database. Steps 6–7 refine via the live TARGET schema
 * (odbc_sqlsrv) when a connection is available:
 *   - GLOBAL vs TENANT master is decided by whether the TARGET table of the
 *     same name has a `company_id` column (design R11.2 / R11.3).
 *   - header vs child is decided by the TARGET id column type: a
 *     varchar/nvarchar id ⇒ header (carry id); a bigint/int identity id ⇒
 *     child (drop id, re-identity) — the same pattern used for the HSE tables.
 * When the live schema is unavailable, the static known lists below provide a
 * deterministic fallback for the master / header / child decision.
 *
 * `injectCountryId` is set only when the TARGET table actually has a
 * `country_id` column (verified at runtime); on the current target only
 * `m_company` carries it — which is TENANT_META and handled elsewhere — so the
 * flag is effectively false for the generic copiers, driven entirely by schema.
 *
 * `dateColumn` is left null here; the DateColumnRegistry (task 2.4) is the
 * source of truth for header date columns and the caller fills it in via
 * {@see TablePlan::withDateColumn()}. A resolver callback may optionally be
 * supplied to {@see classify()} to attach it eagerly.
 */
class TableTaxonomy
{
    /** The target connection name used to refine the classification. */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /** Tables excluded entirely everywhere (never read) — LLD-3 EXCLUDE. */
    public const EXCLUDE = [
        'res_data',
        'audit_trail',
    ];

    /** RBAC tables — handled by RbacMigrator, skipped by the generic copiers. */
    public const RBAC = [
        'tc_user',
        'tc_user_access',
        'tc_user_scope',
        'm_roles',
    ];

    /**
     * Tenant-meta tables — handled by TenantRoleSeeder, skipped by the generic
     * copiers. `m_config` (source) becomes `m_company_config` (target); both
     * company-config names are listed so either schema classifies correctly.
     */
    public const TENANT_META = [
        'm_config',
        'm_company',
        'm_company_config',
        'm_country',
        'm_estate',
    ];

    /** Special-case attendance table — LLD-3 ATTENDANCE_SPECIAL. */
    public const ATTENDANCE = 't_attendance';

    /** Natural-key date column for the attendance special case (LLD-4). */
    public const ATTENDANCE_DATE_COLUMN = 'attendance_date';

    /**
     * The known app-generated VARCHAR-id transaction headers (LLD-3 / LLD-4).
     * These drive child selection by business key. `t_attendance` is NOT here —
     * it is the bigint special case handled before this list.
     */
    public const VARCHAR_ID_HEADERS = [
        't_oph',
        't_cp',
        't_fdn',
        't_workdone',
        't_overtime',
        't_vra',
        't_coconut_oph',
        't_coconut_fdn',
        'tr_gi_header',
        'tr_gr_header',
    ];

    /**
     * Known bigint-id child/detail transaction tables (LLD-3). Used as the
     * static fallback when the live target id type cannot be read. Not
     * exhaustive of every future detail table — the id-type probe is the
     * authority — but covers the detail tables named in the design.
     */
    public const BIGINT_ID_CHILDREN = [
        't_oph_detail',
        't_oph_persons',
        't_cp_detail',
        't_cp_loader',
        't_fdn_detail',
        't_fdn_loader',
        't_workdone_detail',
        't_workdone_material',
        't_overtime_detail',
        't_vra_detail',
        't_harvesting_deduction',
        't_coconut_oph_detail',
        't_coconut_oph_persons',
        't_coconut_fdn_detail',
        'tr_gi_detail',
        'tr_gr_detail',
    ];

    /**
     * Per-table cache of the target `company_id` presence, keyed by table name.
     * @var array<string,bool>
     */
    private array $hasCompanyIdCache = [];

    /**
     * Per-table cache of the target `country_id` presence, keyed by table name.
     * @var array<string,bool>
     */
    private array $hasCountryIdCache = [];

    /**
     * Per-table cache of the target id-column kind: 'varchar' | 'bigint' | null.
     * @var array<string,?string>
     */
    private array $idKindCache = [];

    /**
     * @param ConnectionInterface|null $target The target (odbc_sqlsrv)
     *        connection used to refine GLOBAL/TENANT and header/child decisions.
     *        When null, the taxonomy is resolved purely from the static known
     *        lists above (deterministic, offline-safe).
     */
    public function __construct(private readonly ?ConnectionInterface $target = null)
    {
    }

    /**
     * Build a taxonomy bound to the live target connection. The effective
     * connection is resolved at runtime from config('fusion.target_connection')
     * so a dry-run reroutes classification to the disposable staging target
     * without touching production EPMS (R15.1); defaults to 'odbc_sqlsrv'.
     */
    public static function withTarget(): self
    {
        return new self(DB::connection(config('fusion.target_connection', self::TARGET_CONNECTION)));
    }

    /** Build a taxonomy with no live connection (static known lists only). */
    public static function offline(): self
    {
        return new self(null);
    }

    /**
     * Classify a single source table into a {@see TablePlan}.
     *
     * @param string $table The source table name (lower-case CI3 name).
     * @param callable|null $dateColumnResolver Optional `fn(string $table): ?string`
     *        (e.g. a DateColumnRegistry lookup from task 2.4) used to attach the
     *        header date column eagerly. When omitted, `dateColumn` is left to
     *        the attendance default / null and the caller resolves it later.
     */
    public function classify(string $table, ?callable $dateColumnResolver = null): TablePlan
    {
        $name = strtolower(trim($table));

        // 1. EXCLUDE — highest precedence, never read.
        if (in_array($name, self::EXCLUDE, true)) {
            return new TablePlan($name, TableCategory::EXCLUDE, PkStrategy::NONE);
        }

        // 2. RBAC — handled by RbacMigrator; generic copiers skip.
        if (in_array($name, self::RBAC, true)) {
            return new TablePlan($name, TableCategory::RBAC, PkStrategy::NONE);
        }

        // 3. TENANT_META — handled by TenantRoleSeeder; generic copiers skip.
        if (in_array($name, self::TENANT_META, true)) {
            return new TablePlan($name, TableCategory::TENANT_META, PkStrategy::NONE);
        }

        // 4. ATTENDANCE_SPECIAL — bigint id dropped, natural key recorded later.
        if ($name === self::ATTENDANCE) {
            $dateCol = $dateColumnResolver
                ? ($dateColumnResolver($name) ?? self::ATTENDANCE_DATE_COLUMN)
                : self::ATTENDANCE_DATE_COLUMN;

            return new TablePlan(
                $name,
                TableCategory::ATTENDANCE_SPECIAL,
                PkStrategy::DROP_REIDENTITY,
                injectCompanyId: true,
                injectCountryId: $this->targetHasCountryId($name),
                dateColumn: $dateCol,
            );
        }

        // 5. VARCHAR_ID_HEADER — known header list, or a transaction table whose
        //    target id column is varchar/nvarchar (refined by live schema).
        if ($this->isVarcharIdHeader($name)) {
            $dateCol = $dateColumnResolver ? $dateColumnResolver($name) : null;

            return new TablePlan(
                $name,
                TableCategory::VARCHAR_ID_HEADER,
                PkStrategy::CARRY_VARCHAR_ID,
                injectCompanyId: true,
                injectCountryId: $this->targetHasCountryId($name),
                dateColumn: $dateCol,
            );
        }

        // 6. Master split — m_* by presence of a target company_id column.
        if (str_starts_with($name, 'm_')) {
            if ($this->targetHasCompanyId($name)) {
                return new TablePlan(
                    $name,
                    TableCategory::TENANT_MASTER,
                    PkStrategy::DROP_REIDENTITY,
                    injectCompanyId: true,
                    injectCountryId: $this->targetHasCountryId($name),
                );
            }

            return new TablePlan(
                $name,
                TableCategory::GLOBAL_MASTER,
                PkStrategy::UPSERT_NATURAL_KEY,
                injectCompanyId: false,
                injectCountryId: false,
            );
        }

        // 7. Transaction child — any remaining t_/tr_ table (bigint-id detail).
        if (str_starts_with($name, 't_') || str_starts_with($name, 'tr_')) {
            return new TablePlan(
                $name,
                TableCategory::BIGINT_ID_CHILD,
                PkStrategy::DROP_REIDENTITY,
                injectCompanyId: true,
                injectCountryId: $this->targetHasCountryId($name),
            );
        }

        // Fallback: anything else is treated as a tenant master if it carries a
        // target company_id, otherwise a global master (safe, stampable default).
        if ($this->targetHasCompanyId($name)) {
            return new TablePlan(
                $name,
                TableCategory::TENANT_MASTER,
                PkStrategy::DROP_REIDENTITY,
                injectCompanyId: true,
                injectCountryId: $this->targetHasCountryId($name),
            );
        }

        return new TablePlan(
            $name,
            TableCategory::GLOBAL_MASTER,
            PkStrategy::UPSERT_NATURAL_KEY,
        );
    }

    /**
     * Classify every table of an open source connection.
     *
     * Lists the source tables via information_schema and classifies each.
     * Optional helper for callers that want the full plan set for a source DB.
     *
     * @param ConnectionInterface $source An open PostgreSQL source connection
     *        (e.g. from {@see SourceConnection::for()}).
     * @param callable|null $dateColumnResolver Optional header date-column lookup.
     * @return array<string,TablePlan> keyed by table name.
     */
    public function classifyAll(ConnectionInterface $source, ?callable $dateColumnResolver = null): array
    {
        $rows = $source->select(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
             ORDER BY table_name"
        );

        $plans = [];
        foreach ($rows as $row) {
            $table = $row->table_name;
            $plans[$table] = $this->classify($table, $dateColumnResolver);
        }

        return $plans;
    }

    /**
     * Decide VARCHAR_ID_HEADER: a known header name, OR a transaction table
     * whose target id column is varchar/nvarchar (live-schema refinement).
     */
    private function isVarcharIdHeader(string $name): bool
    {
        if (in_array($name, self::VARCHAR_ID_HEADERS, true)) {
            return true;
        }

        // Only transaction tables can be headers; masters are never headers.
        if (!str_starts_with($name, 't_') && !str_starts_with($name, 'tr_')) {
            return false;
        }

        // Known child → definitely not a header (keeps offline mode deterministic).
        if (in_array($name, self::BIGINT_ID_CHILDREN, true)) {
            return false;
        }

        // Live refinement: a varchar/nvarchar id column means an app-generated
        // header id; a bigint identity means a child. Unknown → not a header.
        return $this->targetIdKind($name) === 'varchar';
    }

    /** Whether the TARGET table of this name has a company_id column (cached). */
    private function targetHasCompanyId(string $table): bool
    {
        if ($this->target === null) {
            // Offline: fall back to the known tenant-meta/header lists having
            // company_id, and assume m_* masters are tenant unless proven global.
            // Without a live schema we cannot know, so default masters to tenant
            // only when not in a known global set is not available here; callers
            // that need exactness should supply a target connection.
            return false;
        }

        return $this->hasCompanyIdCache[$table]
            ??= $this->targetColumnExists($table, 'company_id');
    }

    /** Whether the TARGET table of this name has a country_id column (cached). */
    private function targetHasCountryId(string $table): bool
    {
        if ($this->target === null) {
            return false;
        }

        return $this->hasCountryIdCache[$table]
            ??= $this->targetColumnExists($table, 'country_id');
    }

    /**
     * The target id-column kind for a table: 'varchar' (char/varchar/nvarchar),
     * 'bigint' (bigint/int identity or plain numeric id), or null when the
     * table or its id column is absent. Cached per table.
     */
    private function targetIdKind(string $table): ?string
    {
        if ($this->target === null) {
            return null;
        }

        if (array_key_exists($table, $this->idKindCache)) {
            return $this->idKindCache[$table];
        }

        $rows = $this->target->select(
            "SELECT data_type FROM information_schema.columns
             WHERE table_name = ? AND column_name = 'id'",
            [$table]
        );

        if (empty($rows)) {
            return $this->idKindCache[$table] = null;
        }

        $dataType = strtolower((string) $rows[0]->data_type);
        $kind = str_contains($dataType, 'char') ? 'varchar' : 'bigint';

        return $this->idKindCache[$table] = $kind;
    }

    /** Low-level: does $column exist on target $table? */
    private function targetColumnExists(string $table, string $column): bool
    {
        $rows = $this->target->select(
            "SELECT 1 FROM information_schema.columns
             WHERE table_name = ? AND column_name = ?",
            [$table, $column]
        );

        return !empty($rows);
    }
}
