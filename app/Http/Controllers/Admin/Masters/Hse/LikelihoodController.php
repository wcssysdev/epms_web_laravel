<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\Likelihood;

/**
 * HSE master: Likelihood (portal-synced, read-only).
 *
 * Global / non-tenant master fed by the HSE Portal three-step flow
 * (Get All Data From HSE → Refresh Master Data Info → Replace Master Data),
 * inheriting all of that scaffolding from {@see BaseHseMasterController}. This
 * concrete controller only supplies the per-master contract: the model/table
 * identity, the portal endpoint + staging mirror, the portal→staging and
 * staging→master column projections, and the per-master audit-column override.
 *
 * Sequence-keyed master: the primary key `lhood_id` is an auto-increment identity,
 * so the row needs no manual id — {@see usesManualIdentityKey()} keeps its base
 * default (false) and is NOT overridden, letting the DB assign lhood_id on insert.
 * {@see masterKeyColumn()} is the natural business key `lhood_code`.
 *
 * Portal field list (VERIFIED, camelCase):
 *   id, code, name, description, factors, justification, rating, isActive,
 *   createdDate, modifiedDate.
 *
 * Column-name complexity — the staging mirror `m_master_likelihood` uses
 * portal-shaped names (likelihood_code, likelihood_name, likelihood_rating,
 * description) that differ from the master `m_likelihood` columns (lhood_code,
 * lhood_name, lhood_rating, lhood_description, …). The two projections bridge it:
 *   - {@see mapPortalItem()} projects portal → staging. The mirror has only four
 *     columns, so portal `factors` and `justification` are DROPPED on sync (no
 *     staging column exists for them, hence the master's lhood_factors /
 *     lhood_justification stay NULL after Replace).
 *   - {@see stagingToMasterMap()} projects staging → master
 *     (likelihood_code→lhood_code, likelihood_name→lhood_name,
 *     likelihood_rating→lhood_rating, description→lhood_description).
 *
 * Audit columns: the master uses lhood_*-prefixed audit columns rather than the
 * base default created_by/created_date/..., so {@see addAuditFields()} is overridden.
 *
 * _Requirements: 1.1, 1.2, 1.3, 2.2, 4.1_
 */
class LikelihoodController extends BaseHseMasterController
{
    protected function modelClass(): string
    {
        return Likelihood::class;
    }

    protected function tableName(): string
    {
        return 'm_likelihood';
    }

    protected function stagingTable(): string
    {
        return 'm_master_likelihood';
    }

    protected function resourceName(): string
    {
        return 'Likelihood';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.likelihood';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.likelihood';
    }

    protected function hseEndpoint(): string
    {
        return 'MasterData/likelihoods';
    }

    /** Natural/business key used for dedupe + ordering. */
    protected function masterKeyColumn(): string
    {
        return 'lhood_code';
    }

    protected function datatableColumns(): array
    {
        return [
            'lhood_code' => 'Code',
            'lhood_name' => 'Name',
            'lhood_rating' => 'Rating',
            'lhood_description' => 'Description',
            'is_active' => 'Active',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['lhood_code', 'lhood_name', 'lhood_rating', 'lhood_description'];
    }

    /**
     * Map one portal JSON item to a staging row.
     *
     * Portal `factors` and `justification` have NO staging column in
     * m_master_likelihood and are DROPPED on sync.
     */
    protected function mapPortalItem(array $item): array
    {
        return [
            'likelihood_code' => $item['code'] ?? null,
            'likelihood_name' => $item['name'] ?? null,
            'likelihood_rating' => $item['rating'] ?? null,
            'description' => $item['description'] ?? null,
        ];
    }

    /** Staging → master column projection ([staging_col => master_col]). */
    protected function stagingToMasterMap(): array
    {
        return [
            'likelihood_code' => 'lhood_code',
            'likelihood_name' => 'lhood_name',
            'likelihood_rating' => 'lhood_rating',
            'description' => 'lhood_description',
        ];
    }

    /**
     * Inject the master's lhood_*-prefixed audit columns (not the base
     * created_by/created_date/updated_by/updated_date) before insert during Replace.
     */
    protected function addAuditFields(array $row): array
    {
        $now = now();
        $user = $this->userName();

        $row['lhood_created_timestamp'] = $row['lhood_created_timestamp'] ?? $now;
        $row['lhood_created_by'] = $row['lhood_created_by'] ?? $user;
        $row['lhood_updated_timestamp'] = $now;
        $row['lhood_updated_by'] = $user;

        return $row;
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['lhood_code'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'lhood_code' => $code,
            'lhood_name' => trim($row['lhood_name'] ?? ''),
            'lhood_rating' => trim($row['lhood_rating'] ?? ''),
            'lhood_description' => trim($row['lhood_description'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        return empty($row['lhood_code']) ? 'Likelihood Code is required.' : null;
    }
}
