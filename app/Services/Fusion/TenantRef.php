<?php

namespace App\Services\Fusion;

/**
 * Immutable runtime tenant reference stamped onto outbound rows.
 *
 * Resolved by TenantResolver (a later task) from an estate's company_code /
 * country_code after the tenant tables have been seeded. Unlike
 * SourceDescriptor, this holds the concrete target ids, never hard-coded.
 *
 * See design.md "Internal structures".
 */
final readonly class TenantRef
{
    /**
     * @param int    $companyId   Resolved target m_company id.
     * @param int    $countryId   Resolved target m_country id.
     * @param string $companyCode Business company code (e.g. "1PIP").
     */
    public function __construct(
        public int $companyId,
        public int $countryId,
        public string $companyCode,
    ) {
    }
}
