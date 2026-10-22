<?php

namespace App\Services\Fusion;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds and serves the tenant resolution maps for the fusion ETL and stamps
 * company_id / country_id onto outbound rows.
 *
 * Spec authority:
 *   - design.md LLD-2 (estate -> company/country lookup) and LLD-10
 *     (TenantResolver::stamp() pseudocode incl. the country-normalization comment).
 *   - requirements.md R2.6 (build estate_code -> {company_id, country_id,
 *     company_code} and persist for downstream stages), R2.7 (resolve ids by
 *     code at runtime, never hard-coded), R3.1-R3.4 (country normalization LOCKED).
 *
 * Resolution model (R2.7, never hard-coded):
 *   Codes come from the SourceRegistry manifest (config/fusion.php). The concrete
 *   target identifiers are resolved AT RUNTIME by looking up those codes against
 *   the seeded TARGET tenant tables on the odbc_sqlsrv connection:
 *     - country_code -> m_country.id (by m_country.code)
 *     - company_code -> m_company.id (by m_company.company_code)
 *   Nothing here assumes a numeric id; a code that is not yet seeded is a hard
 *   error that tells the operator to run `fusion:seed-tenants` first.
 *
 * Ordering precondition:
 *   This resolver READS the target tenant tables that TenantRoleSeeder (the
 *   seed-tenants stage) writes. It must therefore run AFTER seeding. Before the
 *   tables are seeded buildFromRegistry() throws a clear "seed first" error.
 *
 * Country normalization (LOCKED, R3): the config/fusion.php manifest already
 * carries SM/rsm26 as MY, but this resolver ALSO forces the SM estate (source
 * db rsm26) to country MY defensively, regardless of any source m_config value,
 * and NEVER assigns the SM estate to country ID. See forEstate()/stamp().
 */
class TenantResolver
{
    /**
     * Ledger entry_type used when persisting the estate -> tenant resolution map
     * so downstream stages (RbacMigrator, MasterCopier, TransactionCopier) can
     * read the resolved ids without re-resolving. Added to the fusion_ledger
     * discriminator vocabulary for this stage.
     */
    public const LEDGER_ENTRY_TYPE = 'tenant_map';

    /**
     * Default target connection name (SQL Server EPMS via ODBC). The effective
     * connection is resolved at runtime from config('fusion.target_connection')
     * so `fusion:run --dry-run` can reroute every target read/write to the
     * disposable staging connection without touching production EPMS (R15.1).
     */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /**
     * Estate code whose source m_config country_code is the 'ID' testing
     * artifact and must be normalized to MY (LOCKED, R3.1/R3.3).
     */
    private const SM_ESTATE_CODE = 'SM';

    /** The normalized country code every estate resolves to (R3.2). */
    private const NORMALIZED_COUNTRY_CODE = 'MY';

    /** @var array<string, TenantRef> estate_code (upper) => TenantRef */
    private array $estateToTenant = [];

    /** @var array<string, int> company_code (upper) => company_id */
    private array $companyCodeToId = [];

    /** @var array<string, int> country_code (upper) => country_id */
    private array $countryCodeToId = [];

    private bool $built = false;

    public function __construct(
        private readonly SourceRegistry $registry,
    ) {
    }

    /**
     * Build estateToTenant / companyCodeToId / countryCodeToId by resolving the
     * manifest codes against the seeded target tenant tables (odbc_sqlsrv).
     *
     * Resolves every distinct country_code to m_country.id (by code) and every
     * distinct company_code to m_company.id (by company_code). Throws a clear
     * "run fusion:seed-tenants first" error when a code is unseeded so the
     * operator seeds before resolving (R2.6/R2.7).
     *
     * @throws RuntimeException when a country_code or company_code is not yet
     *                          present in the target tenant tables.
     */
    public function buildFromRegistry(SourceRegistry $reg): void
    {
        $this->estateToTenant = [];
        $this->companyCodeToId = [];
        $this->countryCodeToId = [];

        $descriptors = $reg->all();

        // Collect distinct codes from the manifest. The SM estate is normalized
        // to MY here so its 'ID' source artifact can never leak into resolution
        // (R3.1/R3.3) — we resolve the MY country id for it, never an ID id.
        $countryCodes = [];
        $companyCodes = [];
        foreach ($descriptors as $descriptor) {
            $countryCode = $this->normalizedCountryCodeFor($descriptor);
            $countryCodes[strtoupper($countryCode)] = $countryCode;
            $companyCodes[strtoupper($descriptor->companyCode)] = $descriptor->companyCode;
        }

        // Resolve country codes -> ids against the seeded target (by code).
        foreach ($countryCodes as $countryCode) {
            $this->countryCodeToId[strtoupper($countryCode)] = $this->resolveCountryId($countryCode);
        }

        // Resolve company codes -> ids against the seeded target (by company_code).
        foreach ($companyCodes as $companyCode) {
            $this->companyCodeToId[strtoupper($companyCode)] = $this->resolveCompanyId($companyCode);
        }

        // Build the estate -> TenantRef map using the resolved ids.
        foreach ($descriptors as $descriptor) {
            $countryCode = $this->normalizedCountryCodeFor($descriptor);

            $this->estateToTenant[strtoupper($descriptor->estateCode)] = new TenantRef(
                companyId: $this->companyCodeToId[strtoupper($descriptor->companyCode)],
                countryId: $this->countryCodeToId[strtoupper($countryCode)],
                companyCode: $descriptor->companyCode,
            );
        }

        $this->built = true;
    }

    /**
     * Resolve the TenantRef for an estate code, or null if the estate is not in
     * the manifest. This is the STABLE contract RowTransformer (task 2.7)
     * depends on — do not change its signature.
     *
     * Lazily builds the maps from the injected SourceRegistry on first use so
     * callers that only want forEstate() do not have to call buildFromRegistry()
     * explicitly.
     */
    public function forEstate(string $estateCode): ?TenantRef
    {
        $this->ensureBuilt();

        return $this->estateToTenant[strtoupper(trim($estateCode))] ?? null;
    }

    /** Resolve company_code -> company_id, or null if not resolved/seeded. */
    public function forCompanyCode(string $companyCode): ?int
    {
        $this->ensureBuilt();

        return $this->companyCodeToId[strtoupper(trim($companyCode))] ?? null;
    }

    /**
     * Resolve country_code -> country_id, or null if not resolved/seeded.
     *
     * Note (R3): the resolver never builds an entry for the 'ID' artifact of SM;
     * passing 'ID' returns null unless a genuinely-ID source exists in the
     * manifest, because SM is normalized to MY at build time.
     */
    public function forCountryCode(string $countryCode): ?int
    {
        $this->ensureBuilt();

        return $this->countryCodeToId[strtoupper(trim($countryCode))] ?? null;
    }

    /**
     * Stamp company_id / country_id onto an outbound row per the TablePlan.
     *
     * Mirrors LLD-10 TenantResolver::stamp(): inject company_id when the plan
     * asks for it, and inject country_id when the plan asks for it AND the row
     * actually carries a country_id column (so we never invent a column the
     * target table does not have).
     *
     * Country normalization (LOCKED, R3): the passed TenantRef is already the
     * resolved, MY-normalized tenant for the estate — SM/rsm26 resolves to the
     * MY country id and never to an ID id. This method therefore cannot stamp an
     * estate with country ID; the normalization happened at resolution time.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function stamp(array $row, TablePlan $plan, TenantRef $tenant): array
    {
        if ($plan->injectCompanyId) {
            $row['company_id'] = $tenant->companyId;
        }

        // Inject country_id only when the plan requests it and the row/target
        // carries the column. The TenantRef->countryId is the normalized MY id
        // for every estate, including SM (R3.3).
        if ($plan->injectCountryId && array_key_exists('country_id', $row)) {
            $row['country_id'] = $tenant->countryId;
        }

        return $row;
    }

    /**
     * Persist the estate -> tenant resolution map to the fusion_ledger so
     * downstream stages can read it without re-resolving (R2.6). Idempotent:
     * each estate's entry is upserted by (entry_type, key_text).
     *
     * key_text  = estate_code (upper)
     * value_text = "company_id|country_id|company_code"
     * company_id = resolved company id (for convenient scoped lookups)
     */
    public function persistToLedger(): void
    {
        $this->ensureBuilt();

        $now = now();

        foreach ($this->estateToTenant as $estateCode => $tenant) {
            $value = $tenant->companyId.'|'.$tenant->countryId.'|'.$tenant->companyCode;

            DB::connection($this->targetConnectionName())->table('fusion_ledger')->updateOrInsert(
                [
                    'entry_type' => self::LEDGER_ENTRY_TYPE,
                    'key_text' => $estateCode,
                ],
                [
                    'value_text' => $value,
                    'company_id' => $tenant->companyId,
                    'message' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    /**
     * The effective target connection name, resolved at runtime so a dry-run
     * reroutes every target read/write to the disposable staging connection.
     * Defaults to the production target 'odbc_sqlsrv' (R15.1).
     */
    private function targetConnectionName(): string
    {
        return (string) config('fusion.target_connection', self::TARGET_CONNECTION);
    }

    /**
     * The normalized country code for a descriptor. Defensively forces the SM
     * estate (source db rsm26) to MY regardless of its manifest/source value
     * (LOCKED, R3.1). The manifest already carries SM as MY, but this guard
     * guarantees SM can never resolve to ID even if the manifest regresses.
     */
    private function normalizedCountryCodeFor(SourceDescriptor $descriptor): string
    {
        if (strtoupper($descriptor->estateCode) === self::SM_ESTATE_CODE) {
            return self::NORMALIZED_COUNTRY_CODE;
        }

        return $descriptor->countryCode;
    }

    /**
     * Resolve a country_code to m_country.id against the seeded target.
     *
     * @throws RuntimeException when the code is not yet seeded.
     */
    private function resolveCountryId(string $countryCode): int
    {
        $id = DB::connection($this->targetConnectionName())
            ->table('m_country')
            ->whereRaw('UPPER(code) = ?', [strtoupper($countryCode)])
            ->value('id');

        if ($id === null) {
            throw new RuntimeException(sprintf(
                'TenantResolver: country_code "%s" is not seeded in m_country. '
                .'Run `php artisan fusion:seed-tenants` first.',
                $countryCode,
            ));
        }

        return (int) $id;
    }

    /**
     * Resolve a company_code to m_company.id against the seeded target.
     *
     * @throws RuntimeException when the code is not yet seeded.
     */
    private function resolveCompanyId(string $companyCode): int
    {
        $id = DB::connection($this->targetConnectionName())
            ->table('m_company')
            ->whereRaw('UPPER(company_code) = ?', [strtoupper($companyCode)])
            ->value('id');

        if ($id === null) {
            throw new RuntimeException(sprintf(
                'TenantResolver: company_code "%s" is not seeded in m_company. '
                .'Run `php artisan fusion:seed-tenants` first.',
                $companyCode,
            ));
        }

        return (int) $id;
    }

    /** Build the maps from the injected registry the first time they are needed. */
    private function ensureBuilt(): void
    {
        if (! $this->built) {
            $this->buildFromRegistry($this->registry);
        }
    }
}
