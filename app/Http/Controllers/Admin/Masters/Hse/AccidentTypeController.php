<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\TypeOfAccident;

/**
 * HSE master: Type of Accident (portal-synced, read-only).
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
 * VERIFIED portal fields (MasterData/type-of-accidents):
 *   id, code, accidentName, afrJkkpName, afrCode, isActive,
 *   createdDate, modifiedDate.
 */
class AccidentTypeController extends BaseHseMasterController
{
    // ── BaseMasterController contract ────────────────────────────────────────

    protected function modelClass(): string
    {
        return TypeOfAccident::class;
    }

    protected function tableName(): string
    {
        return 'm_type_of_accident';
    }

    protected function resourceName(): string
    {
        return 'Type of Accident';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.accident_type';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.accident_type';
    }

    protected function datatableColumns(): array
    {
        return [
            'accident_code' => 'Accident Code',
            'accident_name' => 'Accident Name',
            'afr_jkkp_name' => 'AFR JKKP Name',
            'afr_code' => 'AFR Code',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['accident_code', 'accident_name', 'afr_jkkp_name', 'afr_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['accident_code'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'accident_code' => $code,
            'accident_name' => trim($row['accident_name'] ?? ''),
            'afr_jkkp_name' => trim($row['afr_jkkp_name'] ?? ''),
            'afr_code' => trim($row['afr_code'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['accident_code'])) {
            return 'Accident Code is required.';
        }

        return null;
    }

    // ── BaseHseMasterController contract ─────────────────────────────────────

    protected function hseEndpoint(): string
    {
        return 'MasterData/type-of-accidents';
    }

    protected function stagingTable(): string
    {
        return 'm_master_type_of_accident';
    }

    protected function masterKeyColumn(): string
    {
        return 'accident_code';
    }

    protected function usesManualIdentityKey(): bool
    {
        return true;
    }

    /** Map a portal JSON item → staging row (portal camelCase → staging snake_case). */
    protected function mapPortalItem(array $item): array
    {
        return [
            'accident_code' => $item['code'] ?? null,
            'accident_name' => $item['accidentName'] ?? null,
            'afr_jkkp_name' => $item['afrJkkpName'] ?? null,
            'afr_code' => $item['afrCode'] ?? null,
        ];
    }

    /** Staging → master column map (identity: staging columns mirror master columns). */
    protected function stagingToMasterMap(): array
    {
        return [
            'accident_code' => 'accident_code',
            'accident_name' => 'accident_name',
            'afr_jkkp_name' => 'afr_jkkp_name',
            'afr_code' => 'afr_code',
        ];
    }
}
