<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\Severity;

/**
 * HSE master: Severity (portal-synced, read-only).
 *
 * Global / non-tenant master fed by the HSE Portal three-step flow
 * (Get All Data From HSE → Refresh Master Data Info → Replace Master Data),
 * inheriting all of that scaffolding from {@see BaseHseMasterController}. This
 * concrete controller only supplies the per-master contract: the model/table
 * identity, the portal endpoint + staging mirror, the portal→staging and
 * staging→master column projections, and the per-master audit-column override.
 *
 * Sequence-keyed master: the primary key `id` has a DB sequence default
 * (m_severity_id_seq), so the row needs no manual id — {@see usesManualIdentityKey()}
 * keeps its base default (false) and is NOT overridden, letting the DB assign `id`
 * on insert. {@see masterKeyColumn()} is the natural business key `severity_code`.
 *
 * Portal field list (VERIFIED, camelCase):
 *   id, code, severityName, rating, isActive, createdDate, modifiedDate.
 *
 * Column names: the staging mirror `m_master_severity` (severity_code,
 * severity_name, description, severity_rating) shares the master's business column
 * names, so {@see stagingToMasterMap()} is an identity-named projection. The portal
 * uses camelCase, so {@see mapPortalItem()} renames severityName→severity_name and
 * rating→severity_rating. The portal returns no description for severities, so the
 * staging (and therefore master) `description` is left NULL.
 *
 * Audit columns: the master uses severity_*-prefixed audit columns rather than the
 * base default created_by/created_date/..., so {@see addAuditFields()} is overridden.
 *
 * _Requirements: 1.1, 1.2, 1.3, 2.2, 4.1_
 */
class SeverityController extends BaseHseMasterController
{
    protected function modelClass(): string
    {
        return Severity::class;
    }

    protected function tableName(): string
    {
        return 'm_severity';
    }

    protected function stagingTable(): string
    {
        return 'm_master_severity';
    }

    protected function resourceName(): string
    {
        return 'Severity';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.severity';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.severity';
    }

    protected function hseEndpoint(): string
    {
        return 'MasterData/severities';
    }

    /** Natural/business key used for dedupe + ordering. */
    protected function masterKeyColumn(): string
    {
        return 'severity_code';
    }

    protected function datatableColumns(): array
    {
        return [
            'severity_code' => 'Code',
            'severity_name' => 'Name',
            'severity_rating' => 'Rating',
            'description' => 'Description',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['severity_code', 'severity_name', 'severity_rating', 'description'];
    }

    /**
     * Map one portal JSON item to a staging row.
     *
     * The portal returns no `description` for severities, so the staging column is
     * left null (and the master `description` stays NULL after Replace).
     */
    protected function mapPortalItem(array $item): array
    {
        return [
            'severity_code' => $item['code'] ?? null,
            'severity_name' => $item['severityName'] ?? null,
            'description' => null,
            'severity_rating' => $item['rating'] ?? null,
        ];
    }

    /** Staging → master column projection ([staging_col => master_col]); identity-named. */
    protected function stagingToMasterMap(): array
    {
        return [
            'severity_code' => 'severity_code',
            'severity_name' => 'severity_name',
            'severity_rating' => 'severity_rating',
            'description' => 'description',
        ];
    }

    /**
     * Inject the master's severity_*-prefixed audit columns (not the base
     * created_by/created_date/updated_by/updated_date) before insert during Replace.
     */
    protected function addAuditFields(array $row): array
    {
        $now = now();
        $user = $this->userName();

        $row['severity_created_timestamp'] = $row['severity_created_timestamp'] ?? $now;
        $row['severity_created_by'] = $row['severity_created_by'] ?? $user;
        $row['severity_updated_timestamp'] = $now;
        $row['severity_updated_by'] = $user;

        return $row;
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['severity_code'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'severity_code' => $code,
            'severity_name' => trim($row['severity_name'] ?? ''),
            'severity_rating' => trim($row['severity_rating'] ?? ''),
            'description' => trim($row['description'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        return empty($row['severity_code']) ? 'Severity Code is required.' : null;
    }
}
