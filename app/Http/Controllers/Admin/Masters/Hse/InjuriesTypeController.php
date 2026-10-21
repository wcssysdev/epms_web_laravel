<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\TypeOfInjury;

/**
 * HSE master: Type of Injury (portal-synced, read-only).
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
 * natural business key `type_injury_code`.
 *
 * Portal field list (VERIFIED, camelCase):
 *   id, code, injuryName, afrCode, afrJkkpName, benchmarkEsgName, isActive,
 *   createdDate, modifiedDate.
 * Mapping: code→type_injury_code, injuryName→injury_name, afrCode→afr_code,
 * afrJkkpName→afr_jkkp_name, benchmarkEsgName→benchmark_esg_name. The
 * staging→master projection is identity (staging column names == master column
 * names for these fields).
 *
 * _Requirements: 1.1, 1.2, 2.2, 4.1, 4.8_
 */
class InjuriesTypeController extends BaseHseMasterController
{
    protected function modelClass(): string
    {
        return TypeOfInjury::class;
    }

    protected function tableName(): string
    {
        return 'm_type_of_injury';
    }

    protected function stagingTable(): string
    {
        return 'm_master_type_of_injury';
    }

    protected function resourceName(): string
    {
        return 'Type of Injury';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.injuries_type';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.injuries_type';
    }

    protected function hseEndpoint(): string
    {
        return 'MasterData/type-of-injuries';
    }

    /** Identity-keyed: id assigned manually during Replace (COALESCE(MAX(id),0)+1). */
    protected function usesManualIdentityKey(): bool
    {
        return true;
    }

    /** Natural/business key for dedupe + ordering. */
    protected function masterKeyColumn(): string
    {
        return 'type_injury_code';
    }

    protected function datatableColumns(): array
    {
        return [
            'type_injury_code' => 'Code',
            'injury_name' => 'Injury Name',
            'afr_code' => 'AFR Code',
            'afr_jkkp_name' => 'AFR JKKP Name',
            'benchmark_esg_name' => 'Benchmark ESG Name',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['type_injury_code', 'injury_name', 'afr_code', 'afr_jkkp_name', 'benchmark_esg_name'];
    }

    /** Map one portal JSON item to a staging row. */
    protected function mapPortalItem(array $item): array
    {
        return [
            'type_injury_code' => $item['code'] ?? null,
            'injury_name' => $item['injuryName'] ?? null,
            'afr_code' => $item['afrCode'] ?? null,
            'afr_jkkp_name' => $item['afrJkkpName'] ?? null,
            'benchmark_esg_name' => $item['benchmarkEsgName'] ?? null,
        ];
    }

    /** Staging → master column projection (identity: staging cols == master cols). */
    protected function stagingToMasterMap(): array
    {
        return [
            'type_injury_code' => 'type_injury_code',
            'injury_name' => 'injury_name',
            'afr_code' => 'afr_code',
            'afr_jkkp_name' => 'afr_jkkp_name',
            'benchmark_esg_name' => 'benchmark_esg_name',
        ];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['type_injury_code'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'type_injury_code' => $code,
            'injury_name' => trim($row['injury_name'] ?? ''),
            'afr_code' => trim($row['afr_code'] ?? ''),
            'afr_jkkp_name' => trim($row['afr_jkkp_name'] ?? ''),
            'benchmark_esg_name' => trim($row['benchmark_esg_name'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        return empty($row['type_injury_code']) ? 'Type Injury Code is required.' : null;
    }
}
