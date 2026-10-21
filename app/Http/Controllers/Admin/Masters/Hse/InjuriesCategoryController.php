<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\CategoryOfInjuries;

/**
 * HSE master: Category of Injuries (portal-synced, read-only).
 *
 * Global / non-tenant master fed by the HSE Portal three-step flow
 * (Get All Data From HSE → Refresh Master Data Info → Replace Master Data),
 * inheriting all of that scaffolding from {@see BaseHseMasterController}. This
 * concrete controller only supplies the per-master contract: the model/table
 * identity, the portal endpoint + staging mirror, and the portal→staging and
 * staging→master column projections.
 *
 * Identity-keyed master: the integer `id` has no DB-generated default; it is
 * assigned manually during Replace (COALESCE(MAX(id),0)+1), so
 * {@see usesManualIdentityKey()} returns true and {@see masterKeyColumn()} is the
 * natural business key `category_injuries_code`.
 *
 * Portal field list (VERIFIED, camelCase):
 *   id, code, name, acronym, injuryType, isActive, createdDate, modifiedDate.
 * Mapping decision: `acronym` has no column in the staging mirror and is dropped
 * on sync (code→category_injuries_code, name→category_injuries,
 * injuryType→injuries_type).
 *
 * _Requirements: 1.1, 1.2, 2.2, 4.1, 4.8_
 */
class InjuriesCategoryController extends BaseHseMasterController
{
    protected function modelClass(): string
    {
        return CategoryOfInjuries::class;
    }

    protected function tableName(): string
    {
        return 'm_category_of_injuries';
    }

    protected function stagingTable(): string
    {
        return 'm_master_category_of_injuries';
    }

    protected function resourceName(): string
    {
        return 'Category of Injuries';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.injuries_category';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.injuries_category';
    }

    protected function hseEndpoint(): string
    {
        return 'MasterData/category-of-injuries';
    }

    /** Identity-keyed: id assigned manually during Replace (COALESCE(MAX(id),0)+1). */
    protected function usesManualIdentityKey(): bool
    {
        return true;
    }

    /** Natural/business key for dedupe + ordering. */
    protected function masterKeyColumn(): string
    {
        return 'category_injuries_code';
    }

    protected function datatableColumns(): array
    {
        return [
            'category_injuries_code' => 'Code',
            'category_injuries' => 'Name',
            'injuries_type' => 'Injury Type',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['category_injuries_code', 'category_injuries', 'injuries_type'];
    }

    /**
     * Map one portal JSON item to a staging row. `acronym` is intentionally dropped
     * (no column in m_master_category_of_injuries).
     */
    protected function mapPortalItem(array $item): array
    {
        return [
            'category_injuries_code' => $item['code'] ?? null,
            'category_injuries' => $item['name'] ?? null,
            'injuries_type' => $item['injuryType'] ?? null,
        ];
    }

    /** Staging → master column projection ([staging_col => master_col]). */
    protected function stagingToMasterMap(): array
    {
        return [
            'category_injuries_code' => 'category_injuries_code',
            'category_injuries' => 'category_injuries',
            'injuries_type' => 'injuries_type',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['category_injuries_code'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'category_injuries_code' => $code,
            'category_injuries' => trim($row['category_injuries'] ?? ''),
            'injuries_type' => trim($row['injuries_type'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        return empty($row['category_injuries_code']) ? 'Category Code is required.' : null;
    }
}
