<?php

namespace App\Services\Fusion;

use InvalidArgumentException;

/**
 * Cross-estate user deduplication key for estate-db-fusion
 * (design LLD-7, Requirements R8.1 / R8.7).
 *
 * A single person may appear in several of a company's estate databases
 * (e.g. UNICO = UF + UT, PAMOL = PB + UJ, IOI = SG + BS + SM, DYNAMIC = BD + GE).
 * The RbacMigrator collapses those rows into one `tc_user` by matching on this
 * key, then attaches one estate `tc_user_scope` per estate the identity
 * appeared in.
 *
 * The key is:
 *
 *     DedupKey = COMPANY_CODE . '|' . identity
 *
 * where:
 *   - COMPANY_CODE is the descriptor's company_code, normalized to UPPER+trim so
 *     casing differences never split one company, and
 *   - identity is the FIRST non-empty of, in strict precedence order (R8.1):
 *       1. lower(trim(user_internal_employee_code))
 *       2. lower(trim(user_employee_code))
 *       3. lower(trim(user_login))
 *
 * WITHIN-COMPANY PROPERTY (R8.7): because company_code is part of the key, the
 * same login in two DIFFERENT companies yields two DISTINCT keys and therefore
 * two distinct users — dedup only ever happens inside one company. Conversely,
 * two source users collapse to one key iff they share the same normalized
 * company_code AND the same normalized identity.
 *
 * This class is PURE: it performs NO database access and holds no state. If ALL
 * three identity candidates are empty/blank it throws {@see InvalidArgumentException}
 * — a user with no identity at all is a source data error, and the caller
 * (RbacMigrator) is expected to catch it, record the user to the error ledger,
 * and skip it rather than inventing an identity.
 *
 * @see .kiro/specs/estate-db-fusion/design.md  LLD-7 (cross-estate dedup algorithm)
 */
final class DedupKey
{
    /**
     * Ordered identity candidate source-column names (highest precedence first).
     *
     * Used by {@see fromSourceRow()} to locate the identity in a raw tc_user row.
     * Column names are the CI3 source names; PostgreSQL returns them lower-cased,
     * but fromSourceRow() also tolerates the original casing defensively.
     */
    private const IDENTITY_COLUMNS = [
        'user_internal_employee_code',
        'user_employee_code',
        'user_login',
    ];

    /**
     * Build the dedup key from a company code and the three identity candidates.
     *
     * Returns "{COMPANY_CODE}|{identity}" where COMPANY_CODE is upper+trimmed and
     * identity is the first non-empty of the lower+trimmed candidates, in the
     * R8.1 precedence order (internal employee code -> employee code -> login).
     *
     * @param string      $companyCode          Descriptor company_code, e.g. "1PIP".
     * @param string|null $internalEmployeeCode Highest-precedence identity.
     * @param string|null $employeeCode         Second-precedence identity.
     * @param string|null $userLogin            Lowest-precedence identity.
     *
     * @throws InvalidArgumentException If the company code is blank, or ALL three
     *                                  identity candidates are empty/blank.
     */
    public static function for(
        string $companyCode,
        ?string $internalEmployeeCode,
        ?string $employeeCode,
        ?string $userLogin,
    ): string {
        $company = self::normalizeCompanyCode($companyCode);

        $identity = self::identityOf($internalEmployeeCode, $employeeCode, $userLogin);

        return $company . '|' . $identity;
    }

    /**
     * Convenience wrapper that builds the key from a raw source tc_user row.
     *
     * Reads `user_internal_employee_code`, `user_employee_code`, and `user_login`
     * from the row (tolerating both lower-cased keys, as PostgreSQL returns them,
     * and the original CI3 casing) and defers to {@see for()}.
     *
     * @param array<string,mixed> $row         A source tc_user row.
     * @param string              $companyCode The owning descriptor's company_code.
     *
     * @throws InvalidArgumentException If the company code is blank, or the row
     *                                  carries no non-empty identity candidate.
     */
    public static function fromSourceRow(array $row, string $companyCode): string
    {
        return self::for(
            $companyCode,
            self::readColumn($row, 'user_internal_employee_code'),
            self::readColumn($row, 'user_employee_code'),
            self::readColumn($row, 'user_login'),
        );
    }

    /**
     * Resolve just the normalized identity (the part after the '|'), independent
     * of company — useful for conflict logging and for building keys elsewhere.
     *
     * Returns the first non-empty of lower(trim(...)) over the three candidates
     * in R8.1 precedence order.
     *
     * @throws InvalidArgumentException If all three candidates are empty/blank.
     */
    public static function identityOf(
        ?string $internalEmployeeCode,
        ?string $employeeCode,
        ?string $userLogin,
    ): string {
        foreach ([$internalEmployeeCode, $employeeCode, $userLogin] as $candidate) {
            $normalized = self::normalizeIdentity($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        throw new InvalidArgumentException(
            'Cannot build a DedupKey: all identity candidates '
            . '(user_internal_employee_code, user_employee_code, user_login) are empty.'
        );
    }

    /**
     * Report WHICH source column supplied the chosen identity, for conflict/notes
     * logging (e.g. recording why two estates mapped to the same key).
     *
     * Returns one of 'user_internal_employee_code', 'user_employee_code', or
     * 'user_login' — the first candidate that was non-empty.
     *
     * @throws InvalidArgumentException If all three candidates are empty/blank.
     */
    public static function identityFieldOf(
        ?string $internalEmployeeCode,
        ?string $employeeCode,
        ?string $userLogin,
    ): string {
        $candidates = [
            self::IDENTITY_COLUMNS[0] => $internalEmployeeCode,
            self::IDENTITY_COLUMNS[1] => $employeeCode,
            self::IDENTITY_COLUMNS[2] => $userLogin,
        ];

        foreach ($candidates as $column => $value) {
            if (self::normalizeIdentity($value) !== null) {
                return $column;
            }
        }

        throw new InvalidArgumentException(
            'Cannot resolve a DedupKey identity field: all identity candidates are empty.'
        );
    }

    /**
     * Normalize a company_code: trim then upper-case so casing/whitespace never
     * splits one company into two keys.
     *
     * @throws InvalidArgumentException If the code is empty after trimming.
     */
    private static function normalizeCompanyCode(string $companyCode): string
    {
        $company = strtoupper(trim($companyCode));

        if ($company === '') {
            throw new InvalidArgumentException(
                'Cannot build a DedupKey: company_code is empty.'
            );
        }

        return $company;
    }

    /**
     * Normalize a single identity candidate: trim, then lower-case. Returns null
     * when the candidate is null or blank (so the caller can fall through to the
     * next precedence level).
     */
    private static function normalizeIdentity(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return strtolower($trimmed);
    }

    /**
     * Read a source column tolerating both lower-cased keys (as PostgreSQL
     * returns them) and the original CI3 casing. Returns the raw string value,
     * or null when absent / non-scalar.
     *
     * @param array<string,mixed> $row
     */
    private static function readColumn(array $row, string $column): ?string
    {
        $value = $row[$column] ?? $row[strtoupper($column)] ?? null;

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
