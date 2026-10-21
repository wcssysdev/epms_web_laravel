<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\ProcessLocation;

/**
 * HSE master: Process Location (portal-synced, read-only).
 *
 * Global / non-tenant master fed by the HSE Portal three-step flow
 * (Get All Data From HSE → Refresh Master Data Info → Replace Master Data),
 * inheriting all of that scaffolding from {@see BaseHseMasterController}. This
 * concrete controller only supplies the per-master contract: the model/table
 * identity, the portal endpoint + staging mirror, the portal→staging and
 * staging→master column projections, and the per-master audit-column override.
 *
 * Natural-key master: the primary key `ploc_id` is a varchar business key, so the
 * row carries no DB-generated identity default — {@see usesManualIdentityKey()}
 * keeps its base default (false) and is NOT overridden. {@see masterKeyColumn()}
 * is the natural key `ploc_id`.
 *
 * Portal field list (VERIFIED, camelCase):
 *   id, code, description, status, revNo, isActive, createdDate, modifiedDate.
 *
 * Column-name complexity — the staging mirror `m_master_process_location` uses
 * portal-shaped names (process_location_code, process_location_name, description)
 * that differ from the master `m_process_location` columns (ploc_id, ploc_desc,
 * ploc_status, ploc_rev_no). The two projections bridge the gap:
 *   - {@see mapPortalItem()} projects portal → staging. Only the three staging
 *     columns exist, so portal `status` and `revNo` are DROPPED on sync (the mirror
 *     has no column for them); the portal `description` field is likewise dropped
 *     because the mirror's own `description` column carries the location NAME
 *     (code→process_location_code, description→process_location_name).
 *   - {@see stagingToMasterMap()} projects staging → master
 *     (process_location_code→ploc_id, process_location_name→ploc_desc). The staging
 *     mirror carries no status/rev_no, so master `ploc_status`/`ploc_rev_no` are
 *     left unmapped (NULL) after Replace.
 *
 * Audit columns: the master uses ploc_*-prefixed audit columns rather than the
 * base default created_by/created_date/..., so {@see addAuditFields()} is overridden.
 *
 * _Requirements: 1.1, 1.2, 1.3, 2.2, 4.1_
 */
class ProcessLocationController extends BaseHseMasterController
{
    protected function modelClass(): string
    {
        return ProcessLocation::class;
    }

    protected function tableName(): string
    {
        return 'm_process_location';
    }

    protected function stagingTable(): string
    {
        return 'm_master_process_location';
    }

    protected function resourceName(): string
    {
        return 'Process Location';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.process_location';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.process_location';
    }

    protected function hseEndpoint(): string
    {
        return 'MasterData/process-locations';
    }

    /** Natural/business key (varchar) used for dedupe + ordering. */
    protected function masterKeyColumn(): string
    {
        return 'ploc_id';
    }

    protected function datatableColumns(): array
    {
        return [
            'ploc_id' => 'Code',
            'ploc_desc' => 'Description',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['ploc_id', 'ploc_desc'];
    }

    /**
     * Map one portal JSON item to a staging row.
     *
     * The mirror has only process_location_code / process_location_name /
     * description. Portal `code`→process_location_code and portal
     * `description`→process_location_name (the location's display label).
     * Portal `status` and `revNo` have NO staging column and are DROPPED on sync.
     * The mirror's `description` column is left null (no distinct portal source).
     */
    protected function mapPortalItem(array $item): array
    {
        return [
            'process_location_code' => $item['code'] ?? null,
            'process_location_name' => $item['description'] ?? null,
            'description' => null,
        ];
    }

    /**
     * Staging → master column projection ([staging_col => master_col]).
     *
     * Only the two populated mirror columns resolve to master columns; the master's
     * ploc_status / ploc_rev_no have no staging source and remain NULL after Replace.
     */
    protected function stagingToMasterMap(): array
    {
        return [
            'process_location_code' => 'ploc_id',
            'process_location_name' => 'ploc_desc',
        ];
    }

    /**
     * Inject the master's ploc_*-prefixed audit columns (not the base
     * created_by/created_date/updated_by/updated_date) before insert during Replace.
     */
    protected function addAuditFields(array $row): array
    {
        $now = now();
        $user = $this->userName();

        $row['ploc_created_timestamp'] = $row['ploc_created_timestamp'] ?? $now;
        $row['ploc_created_by'] = $row['ploc_created_by'] ?? $user;
        $row['ploc_updated_timestamp'] = $now;
        $row['ploc_updated_by'] = $user;

        return $row;
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['ploc_id'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'ploc_id' => $code,
            'ploc_desc' => trim($row['ploc_desc'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        return empty($row['ploc_id']) ? 'Process Location Code is required.' : null;
    }
}
