<?php

namespace App\Services\Fusion;

/**
 * Immutable description of a single CI3 source estate database.
 *
 * One SourceDescriptor is built per entry in config('fusion.sources') by
 * SourceRegistry. It carries only the static manifest facts; the runtime
 * tenant ids (company_id / country_id) are resolved later by TenantResolver
 * from companyCode / countryCode and are intentionally NOT stored here.
 *
 * See design.md "Internal structures".
 */
final readonly class SourceDescriptor
{
    /**
     * @param string $dbName      Physical source database name (e.g. "sagil_mei").
     * @param string $countryCode ISO-ish country code (e.g. "MY"); may be overridden in the manifest.
     * @param string $companyCode Business company code (e.g. "1PIP").
     * @param string $estateCode  Estate code (e.g. "SG").
     * @param string $companyName Human-readable company name.
     * @param int    $tableClass  Table-count class: 136 or 200.
     */
    public function __construct(
        public string $dbName,
        public string $countryCode,
        public string $companyCode,
        public string $estateCode,
        public string $companyName,
        public int $tableClass,
    ) {
    }

    /**
     * Build a descriptor from one config('fusion.sources') assoc array.
     *
     * Maps snake_case manifest keys to the value object's camelCase fields:
     *   db_name → dbName, country_code → countryCode, company_code → companyCode,
     *   estate_code → estateCode, company_name → companyName, table_class → tableClass.
     *
     * @param array{
     *     db_name:string, country_code:string, company_code:string,
     *     estate_code:string, company_name:string, table_class:int|string
     * } $row
     */
    public static function fromConfigArray(array $row): self
    {
        return new self(
            dbName: (string) $row['db_name'],
            countryCode: (string) $row['country_code'],
            companyCode: (string) $row['company_code'],
            estateCode: (string) $row['estate_code'],
            companyName: (string) $row['company_name'],
            tableClass: (int) $row['table_class'],
        );
    }
}
