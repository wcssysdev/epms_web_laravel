<?php

namespace App\Services\Fusion;

use Illuminate\Database\Connection;

/**
 * Per-header-table date-column registry for the transaction window (LLD-4).
 *
 * Transaction tables do NOT share a date column. The TransactionCopier computes
 * a window `AnchorDate − N months` and filters each header table by its OWN date
 * column; children are selected by the parent business key, never by date
 * (requirements R12.1, R12.3). This registry encodes the authoritative
 * header-table → date-column map from design LLD-4.
 *
 * The registry is a pure static map with no database dependency: {@see dateColumn()}
 * resolves a header to its configured column (or null when the table is not a
 * known header). Build-time verification against the live source schema is a
 * SEPARATE, lazy concern handled by {@see validateAgainst()}, which the
 * TransactionCopier calls to confirm the column actually exists in the currently
 * open source database. When a header has no resolvable date column, the lookup /
 * validation returns null, and the TransactionCopier treats the table as a full
 * copy with a logged warning (R12.9).
 *
 * @see .kiro/specs/estate-db-fusion/design.md  LLD-4 (per-table date-column registry)
 */
class DateColumnRegistry
{
    /**
     * Authoritative header-table → ORDERED CI3 date-column CANDIDATES (design LLD-4).
     *
     * Each transaction header filters on its own prefixed date column. The value
     * is an ORDERED list of candidate columns: {@see validateAgainst()} returns
     * the FIRST candidate that actually exists in the currently open source
     * database, so a header whose primary design column is absent but whose
     * documented alternate IS present still resolves (it is not forced down the
     * R12.9 full-copy+warn path). {@see dateColumn()} returns the PRIMARY (first)
     * candidate for pure, offline callers — backward compatible with the single
     * string the registry exposed before task 8.1.
     *
     * The candidate order is authoritative; the exact names were CONFIRMED
     * against the live CI3 source schema (task 8.1 probe of sagil_mei / rsm26 /
     * gomali26). See the per-table notes below for what the probe found.
     *
     * @var array<string, list<string>>
     */
    protected const MAP = [
        // Core palm transaction headers — single confirmed column each.
        't_attendance'  => ['attendance_date'],      // leaf; special-case natural key
        't_oph'         => ['oph_created_date'],      // also has oph_approved_date; window anchor/filter is oph_created_date
        't_cp'          => ['cp_created_date'],
        't_fdn'         => ['fdn_created_date'],
        't_workdone'    => ['workdone_date'],
        't_overtime'    => ['overtime_date'],

        // t_vra — LLD-4 lists `vra_date` / `vra_created_date`. CONFIRMED (task
        // 8.1 probe): `vra_date` is ABSENT in every probed source; the real
        // column is `vra_created_date` (a DATE, matching the *_created_date
        // convention of the other headers). vra_date is kept first (design
        // primary) so validateAgainst() tries it, then falls back to the
        // confirmed `vra_created_date`. The TransactionCopier therefore windows
        // t_vra on vra_created_date rather than full-copying it (R12.3/R12.9).
        't_vra'         => ['vra_date', 'vra_created_date'],

        // Coconut transaction headers (rsm26 / gomali26 — module exists in target).
        't_coconut_oph' => ['coconut_oph_created_date'],
        't_coconut_fdn' => ['coconut_fdn_created_date'],

        // GI / GR warehouse transaction headers. CONFIRMED (task 8.1 probe): the
        // design's `gi_date` / `gr_date` do NOT exist; the only real timestamp is
        // `tr_gi_header_created_timestamp` / `tr_gr_header_created_timestamp`
        // (`*_doc_date` / `*_pstg_date` exist but are character varying — not a
        // reliable date filter). The design primary is kept first, then the
        // confirmed `*_created_timestamp` fallback, so GI/GR window on the
        // created timestamp instead of being full-copied (R12.3/R12.9).
        'tr_gi_header'  => ['gi_date', 'tr_gi_header_created_timestamp'],
        'tr_gr_header'  => ['gr_date', 'tr_gr_header_created_timestamp'],
    ];

    /**
     * Resolve the PRIMARY configured date column for a header table (pure, no DB
     * access). Returns the first candidate in the table's candidate list — the
     * design-primary column — so pure/offline callers keep the historical
     * single-column contract. Prefer {@see validateAgainst()} at runtime, which
     * falls back through the remaining candidates against the live schema.
     *
     * @param  string  $table  Source header table name (e.g. `t_oph`).
     * @return string|null  The primary configured date column, or null when
     *                      $table is not a known header (caller treats this as
     *                      full-copy+warn, R12.9).
     */
    public static function dateColumn(string $table): ?string
    {
        return self::MAP[$table][0] ?? null;
    }

    /**
     * The ordered candidate date columns for a header table (pure, no DB access).
     *
     * @return list<string>  Empty list when $table is not a known header.
     */
    public static function candidates(string $table): array
    {
        return self::MAP[$table] ?? [];
    }

    /**
     * Whether $table is a known header with a configured date column.
     */
    public static function isHeader(string $table): bool
    {
        return isset(self::MAP[$table]);
    }

    /**
     * The full header-table → PRIMARY date-column map (copy of the static
     * registry, flattened to the primary candidate per table for a stable,
     * single-string view — backward compatible with the pre-8.1 shape).
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::MAP as $table => $candidates) {
            $out[$table] = $candidates[0];
        }

        return $out;
    }

    /**
     * Validate the configured date column(s) against the live source schema (lazy).
     *
     * Walks the ORDERED candidate list for $table (design-primary first, then any
     * confirmed fallbacks) and returns the FIRST candidate that actually exists
     * in `information_schema.columns` of the currently open source database. When
     * NO candidate exists (or $table is not a known header at all) it returns
     * null, which the TransactionCopier treats as a full copy with a logged
     * warning (R12.9).
     *
     * This lets a header whose design-primary column is absent in the real CI3
     * schema (e.g. t_vra's `vra_date`, or tr_gi_header's `gi_date`) still window
     * correctly on its confirmed alternate (`vra_created_date`,
     * `tr_gi_header_created_timestamp`) instead of being forced down the
     * full-copy path — while a header with genuinely no date column still
     * resolves to null and is full-copied.
     *
     * This is the only method that touches the database; the registry itself is
     * usable without a connection.
     *
     * @param  Connection  $sourceConn  An open PostgreSQL source connection.
     * @param  string      $table       Source header table name.
     * @return string|null  The first resolved, verified date column, or null.
     */
    public static function validateAgainst(Connection $sourceConn, string $table): ?string
    {
        foreach (self::candidates($table) as $column) {
            $exists = $sourceConn->table('information_schema.columns')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->exists();

            if ($exists) {
                return $column;
            }
        }

        return null;
    }
}
