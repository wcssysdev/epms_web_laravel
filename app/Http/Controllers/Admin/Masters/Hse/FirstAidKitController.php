<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\FirstAidKit;

/**
 * HSE master: First Aid Kit (portal-synced, read-only).
 *
 * Thin subclass of {@see BaseHseMasterController}. The master is GLOBAL (no
 * company_id) and identity-keyed — the HSE Portal supplies no DB-generated id
 * default, so {@see usesManualIdentityKey()} returns true and the Replace step
 * assigns each row a sequential id (COALESCE(MAX(id),0)+1).
 *
 * Portal sync is read-only: no create/store actions are exposed (the "Add"
 * button is hidden in the index view, mirroring the SAP-synced VraController).
 * Blade views and routes are wired up separately (tasks 6.5 / 9.1).
 *
 * VERIFIED portal fields (MasterData/first-aid-kits):
 *   id, code, kitName, uom, isActive, createdDate, modifiedDate.
 *
 * NOTE: the portal does NOT return estate_code / division_code. The staging
 * mirror (m_master_first_aid_kit) carries only kit_code / kit_name / uom, so
 * those two master columns stay null after a portal sync — they are not
 * invented in the staging projection. The master table additionally carries an
 * is_active flag (surfaced in the datatable to display the active state).
 */
class FirstAidKitController extends BaseHseMasterController
{
    // ── BaseMasterController contract ────────────────────────────────────────

    protected function modelClass(): string
    {
        return FirstAidKit::class;
    }

    protected function tableName(): string
    {
        return 'm_first_aid_kit';
    }

    protected function resourceName(): string
    {
        return 'First Aid Kit';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.first_aid_kit';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.first_aid_kit';
    }

    protected function datatableColumns(): array
    {
        return [
            'kit_code' => 'Kit Code',
            'kit_name' => 'Kit Name',
            'uom' => 'UOM',
            'is_active' => 'Active',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['kit_code', 'kit_name', 'uom'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['kit_code'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'kit_code' => $code,
            'kit_name' => trim($row['kit_name'] ?? ''),
            'uom' => trim($row['uom'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['kit_code'])) {
            return 'Kit Code is required.';
        }

        return null;
    }

    // ── BaseHseMasterController contract ─────────────────────────────────────

    protected function hseEndpoint(): string
    {
        return 'MasterData/first-aid-kits';
    }

    protected function stagingTable(): string
    {
        return 'm_master_first_aid_kit';
    }

    protected function masterKeyColumn(): string
    {
        return 'kit_code';
    }

    protected function usesManualIdentityKey(): bool
    {
        return true;
    }

    /**
     * Map a portal JSON item → staging row. The portal returns no
     * estate_code/division_code, so the staging mirror only holds
     * kit_code / kit_name / uom (those two master columns stay null on sync).
     */
    protected function mapPortalItem(array $item): array
    {
        return [
            'kit_code' => $item['code'] ?? null,
            'kit_name' => $item['kitName'] ?? null,
            'uom' => $item['uom'] ?? null,
        ];
    }

    /** Staging → master column map (identity: staging columns mirror master columns). */
    protected function stagingToMasterMap(): array
    {
        return [
            'kit_code' => 'kit_code',
            'kit_name' => 'kit_name',
            'uom' => 'uom',
        ];
    }
}
