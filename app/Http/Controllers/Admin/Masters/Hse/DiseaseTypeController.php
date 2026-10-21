<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\TypeOfDisease;

/**
 * HSE master: Type of Disease (portal-synced, read-only).
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
 * VERIFIED portal fields (MasterData/type-of-diseases):
 *   id, code, diseaseName, diseaseCategory, afrCode, afrJkkpName,
 *   benchmarkName, isActive, createdDate, modifiedDate.
 */
class DiseaseTypeController extends BaseHseMasterController
{
    // ── BaseMasterController contract ────────────────────────────────────────

    protected function modelClass(): string
    {
        return TypeOfDisease::class;
    }

    protected function tableName(): string
    {
        return 'm_type_of_disease';
    }

    protected function resourceName(): string
    {
        return 'Type of Disease';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.disease_type';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.disease_type';
    }

    protected function datatableColumns(): array
    {
        return [
            'disease_code' => 'Disease Code',
            'disease_name' => 'Disease Name',
            'disease_category' => 'Category',
            'afr_code' => 'AFR Code',
            'afr_jkkp_name' => 'AFR JKKP Name',
            'benchmark_name' => 'Benchmark Name',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['disease_code', 'disease_name', 'disease_category', 'afr_code', 'afr_jkkp_name', 'benchmark_name'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['disease_code'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'disease_code' => $code,
            'disease_name' => trim($row['disease_name'] ?? ''),
            'disease_category' => trim($row['disease_category'] ?? ''),
            'afr_code' => trim($row['afr_code'] ?? ''),
            'afr_jkkp_name' => trim($row['afr_jkkp_name'] ?? ''),
            'benchmark_name' => trim($row['benchmark_name'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['disease_code'])) {
            return 'Disease Code is required.';
        }

        return null;
    }

    // ── BaseHseMasterController contract ─────────────────────────────────────

    protected function hseEndpoint(): string
    {
        return 'MasterData/type-of-diseases';
    }

    protected function stagingTable(): string
    {
        return 'm_master_type_of_disease';
    }

    protected function masterKeyColumn(): string
    {
        return 'disease_code';
    }

    protected function usesManualIdentityKey(): bool
    {
        return true;
    }

    /** Map a portal JSON item → staging row (portal camelCase → staging snake_case). */
    protected function mapPortalItem(array $item): array
    {
        return [
            'disease_code' => $item['code'] ?? null,
            'disease_name' => $item['diseaseName'] ?? null,
            'disease_category' => $item['diseaseCategory'] ?? null,
            'afr_code' => $item['afrCode'] ?? null,
            'afr_jkkp_name' => $item['afrJkkpName'] ?? null,
            'benchmark_name' => $item['benchmarkName'] ?? null,
        ];
    }

    /** Staging → master column map (identity: staging columns mirror master columns). */
    protected function stagingToMasterMap(): array
    {
        return [
            'disease_code' => 'disease_code',
            'disease_name' => 'disease_name',
            'disease_category' => 'disease_category',
            'afr_code' => 'afr_code',
            'afr_jkkp_name' => 'afr_jkkp_name',
            'benchmark_name' => 'benchmark_name',
        ];
    }
}
