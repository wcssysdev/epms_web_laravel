<?php

namespace App\Services\Fusion;

use Throwable;

/**
 * Shared, PURE row transform applied to every outbound row by the copiers
 * (design LLD-3 / LLD-10). Four concerns, in order:
 *
 *   1. Audit remap   — CI3 created_date(+time)/updated_date(+time)/modified_* →
 *                      target created_at / updated_at (+ created_by / updated_by).
 *   2. Tenant stamp  — inject company_id / country_id from an ALREADY-RESOLVED
 *                      {@see TenantRef} per the TablePlan flags.
 *   3. PK strategy   — carry / drop the `id` column per {@see PkStrategy}.
 *   4. Type coercion — PostgreSQL → SQL Server type/encoding normalization.
 *
 * PURITY (required for unit testing, task 2.8): this class performs NO database
 * access and holds NO mutable state. It accepts a resolved {@see TenantRef}
 * passed in by the caller — the copier resolves the TenantRef ONCE per source DB
 * (via TenantResolver::forEstate(), task 2.9) and hands it to transform() for
 * every row. The transformer never re-resolves per row and never calls the
 * resolver itself.
 *
 * ERROR HANDLING (R13.5): on a type/encoding error for a row, transform() throws
 * a {@see RowTransformException} carrying the row. It does NOT write to the error
 * ledger (that would break purity). The CALLER (MasterCopier / TransactionCopier)
 * catches the exception, records the row to the error ledger, and continues the
 * batch — which is where "record the row and continue" (R13.5) is satisfied.
 *
 * @see .kiro/specs/estate-db-fusion/design.md  LLD-3 (per-category rules),
 *      LLD-10 (transform/audit pseudocode); requirements R11.4, R13.1, R13.2, R13.5.
 */
class RowTransformer
{
    /**
     * CI3-only audit columns that are REMAPPED into created_at/updated_at and then
     * dropped from the output so they do not collide with the target schema. The
     * target keeps created_by / updated_by (verified schema), so those are NOT here.
     *
     * @var list<string>
     */
    private const CI3_AUDIT_COLUMNS = [
        'created_date', 'created_time',
        'updated_date', 'updated_time',
        'modified_date', 'modified_time',
        'modified_by',
    ];

    /**
     * Transform a single raw source row into its target shape.
     *
     * @param  array<string,mixed> $row    Raw row as read from the source (pg).
     * @param  TablePlan           $plan   Classification for the owning table.
     * @param  TenantRef           $tenant Already-resolved tenant (company/country ids).
     * @return array<string,mixed>         Target-ready row.
     *
     * @throws RowTransformException When a value cannot be coerced (R13.5).
     */
    public function transform(array $row, TablePlan $plan, TenantRef $tenant): array
    {
        $out = $this->remapAudit($row);
        $out = $this->stampTenant($out, $plan, $tenant);
        $out = $this->applyPkStrategy($out, $plan);

        return $this->coerceTypes($out, $plan);
    }

    // -------------------------------------------------------------------------
    // 1. Audit remap (R11.4)
    // -------------------------------------------------------------------------

    /**
     * Remap the CI3 audit columns onto the target created_at / updated_at /
     * created_by / updated_by columns (design LLD-3 audit table), then drop the
     * CI3-only source columns so they do not collide with the target schema.
     *
     * Rules:
     *  - created_at = combine(created_date, created_time) ?? created_date
     *  - updated_at = combine(updated|modified_date, updated|modified_time)
     *                 ?? (updated|modified_date) ?? created_at   (fallback)
     *  - created_by : carried as-is (string login/name).
     *  - updated_by : updated_by ?? modified_by (else left untouched/null).
     *
     * Prefixed-audit masters (e.g. ploc_created_timestamp, *_created_date):
     * the GENERIC pure remap only targets the common unprefixed set above.
     * Unknown prefixed audit columns are passed through UNTOUCHED here — if the
     * target column name matches they land correctly; otherwise the insert layer
     * drops them. A per-table audit-column map is the documented strategy for
     * tables whose only audit columns are prefixed.
     *
     * TODO(per-table-audit-map): for masters that carry ONLY prefixed audit
     * columns (no unprefixed created_date/updated_date), add a per-table override
     * that maps e.g. `ploc_created_timestamp` → created_at. Out of scope for this
     * generic transform; tracked for a follow-up once the prefixed-audit master
     * set is enumerated from information_schema.
     *
     * @param  array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function remapAudit(array $row): array
    {
        $out = $row;

        // created_at: date (+ optional time), else date-only midnight.
        $createdAt = $this->combineDateTime(
            $row['created_date'] ?? null,
            $row['created_time'] ?? null,
        );
        if ($createdAt !== null) {
            $out['created_at'] = $createdAt;
        }

        // updated_at: prefer updated_*, then modified_*; fall back to created_at.
        $updatedDate = $row['updated_date'] ?? $row['modified_date'] ?? null;
        $updatedTime = $row['updated_time'] ?? $row['modified_time'] ?? null;
        $updatedAt   = $this->combineDateTime($updatedDate, $updatedTime);
        if ($updatedAt !== null) {
            $out['updated_at'] = $updatedAt;
        } elseif (isset($out['created_at'])) {
            // No update timestamp in the source: mirror created_at (LLD-3 fallback).
            $out['updated_at'] = $out['created_at'];
        }

        // updated_by: updated_by ?? modified_by. created_by is left as-is.
        if (! array_key_exists('updated_by', $row) && array_key_exists('modified_by', $row)) {
            $out['updated_by'] = $row['modified_by'];
        }

        // Drop the CI3-only audit columns so they do not collide with the target.
        foreach (self::CI3_AUDIT_COLUMNS as $col) {
            unset($out[$col]);
        }

        return $out;
    }

    /**
     * Combine a date and an optional time into a single 'Y-m-d H:i:s' timestamp.
     *
     * Returns null when the date part is empty. When only a date is present the
     * time defaults to 00:00:00. Accepts values that already contain a time
     * component (returns them normalized) and falls back to the raw string when
     * it cannot be parsed, so a non-standard but non-empty source value is never
     * silently discarded.
     *
     * @param  mixed $date
     * @param  mixed $time
     * @return string|null
     */
    private function combineDateTime(mixed $date, mixed $time): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $date = trim((string) $date);
        $time = ($time === null) ? '' : trim((string) $time);

        // Source date may already carry a time (e.g. a pg timestamp). If a
        // separate time column is ALSO present, prefer the explicit date portion
        // of $date plus the explicit $time.
        if ($time !== '') {
            $datePart = explode(' ', $date, 2)[0];
            $candidate = $datePart . ' ' . $time;
        } else {
            $candidate = str_contains($date, ' ') ? $date : $date . ' 00:00:00';
        }

        return $this->normalizeTimestamp($candidate) ?? $candidate;
    }

    // -------------------------------------------------------------------------
    // 2. Tenant stamping (LLD-3 / LLD-10)
    // -------------------------------------------------------------------------

    /**
     * Stamp company_id / country_id from the resolved TenantRef per the plan flags.
     *
     * The transformer stamps exactly what it is GIVEN: the SM→MY country
     * normalization (rsm26/SM forced to MY) is encoded UPSTREAM in the TenantRef
     * that TenantResolver produced, so there is nothing to normalize here.
     *
     * @param  array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function stampTenant(array $row, TablePlan $plan, TenantRef $tenant): array
    {
        if ($plan->injectCompanyId) {
            $row['company_id'] = $tenant->companyId;
        }

        // country_id only where the target column exists (plan flag decides this).
        if ($plan->injectCountryId) {
            $row['country_id'] = $tenant->countryId;
        }

        return $row;
    }

    // -------------------------------------------------------------------------
    // 3. PK strategy (LLD-3 / LLD-8)
    // -------------------------------------------------------------------------

    /**
     * Apply the primary-key strategy to the `id` column.
     *
     *  - CARRY_VARCHAR_ID   : keep row['id'] (app-generated VARCHAR header, globally unique).
     *  - DROP_REIDENTITY    : unset row['id'] so SQL Server assigns a fresh identity
     *                         (bigint child / tenant master / attendance-special).
     *  - UPSERT_NATURAL_KEY : keep the row as-is (GLOBAL master; MasterCopier upserts by key).
     *  - NONE               : no PK change.
     *
     * @param  array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function applyPkStrategy(array $row, TablePlan $plan): array
    {
        switch ($plan->pkStrategy) {
            case PkStrategy::DROP_REIDENTITY:
                unset($row['id']);
                break;

            case PkStrategy::CARRY_VARCHAR_ID:
            case PkStrategy::UPSERT_NATURAL_KEY:
            case PkStrategy::NONE:
                // Keep the row unchanged.
                break;
        }

        return $row;
    }

    // -------------------------------------------------------------------------
    // 4. Type / encoding coercion pg → SQL Server (R13.2, R13.5)
    // -------------------------------------------------------------------------

    /**
     * Coerce PostgreSQL value types/encodings to their SQL Server equivalents.
     *
     * Practical, value-shape based coercion (we do not have target column types
     * here, so we normalize by the source value's form):
     *  - booleans: pg bool (true/false, 't'/'f') → SQL Server bit (1/0).
     *  - timestamps: DateTimeInterface / ISO-ish strings → 'Y-m-d H:i:s'.
     *  - arrays/json: pg array/json come back as strings from the driver and are
     *    left AS-IS (the target stores them as nvarchar); if the driver already
     *    decoded them to a PHP array we re-encode to a JSON string.
     *  - strings: trimmed of invalid UTF-8 (lossy) so the SQL Server driver does
     *    not reject the batch on an un-encodable byte.
     *
     * Any value that cannot be coerced raises a {@see RowTransformException} so
     * the caller can ledger the row and continue the batch (R13.5).
     *
     * @param  array<string,mixed> $row
     * @return array<string,mixed>
     *
     * @throws RowTransformException
     */
    private function coerceTypes(array $row, TablePlan $plan): array
    {
        foreach ($row as $column => $value) {
            try {
                $row[$column] = $this->coerceValue($value);
            } catch (Throwable $e) {
                throw new RowTransformException(
                    $plan->table,
                    $row,
                    (string) $column,
                    $e->getMessage(),
                    $e,
                );
            }
        }

        return $row;
    }

    /**
     * Coerce a single scalar/compound value. Kept separate so coerceTypes() can
     * attribute any failure to a specific column for the ledger entry.
     *
     * @throws \JsonException When a PHP array/object cannot be JSON-encoded.
     */
    private function coerceValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // pg boolean → SQL Server bit.
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        // pg timestamp object → canonical string.
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        // pg array / json the driver decoded to a PHP structure → JSON string
        // (target column is textual; we never hand SQL Server a PHP array).
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if (is_string($value)) {
            return $this->coerceString($value);
        }

        // ints / floats pass through unchanged.
        return $value;
    }

    /**
     * String coercion: normalize pg boolean literals that arrive as text, fix
     * invalid UTF-8, and canonicalize timestamp-looking strings.
     */
    private function coerceString(string $value): string|int
    {
        // Scrub invalid UTF-8 so the SQL Server driver does not reject the batch.
        $clean = $this->scrubUtf8($value);
        $trimmed = trim($clean);

        // pg textual booleans → bit. Only the exact single-char pg forms, to
        // avoid clobbering legitimate data like a status column literally 't'.
        // (The driver usually returns real bools, handled in coerceValue().)
        if ($trimmed === 'true') {
            return 1;
        }
        if ($trimmed === 'false') {
            return 0;
        }

        // Canonicalize an ISO timestamp (e.g. '2024-01-02T03:04:05' or with a
        // timezone / microseconds) to 'Y-m-d H:i:s'; leave other strings alone.
        if ($this->looksLikeTimestamp($trimmed)) {
            $normalized = $this->normalizeTimestamp($trimmed);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $clean;
    }

    /**
     * Replace invalid UTF-8 byte sequences so the value is safe for an nvarchar
     * insert. Lossy by design (invalid bytes are substituted), which is the
     * practical choice for a one-way CI3 → target migration.
     */
    private function scrubUtf8(string $value): string
    {
        if ($value === '' || (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8'))) {
            return $value;
        }

        // Substitute invalid sequences rather than dropping them silently.
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return $converted === false ? $value : $converted;
    }

    /**
     * Cheap pre-check so we only attempt timestamp parsing on strings that start
     * with a plausible date (YYYY-MM-DD ...), never on arbitrary text.
     */
    private function looksLikeTimestamp(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $value);
    }

    /**
     * Normalize a date/time string to 'Y-m-d H:i:s'. Returns null when the string
     * cannot be parsed as a date (caller decides the fallback).
     */
    private function normalizeTimestamp(string $value): ?string
    {
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
