<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\RiskPriorityOrder;

/**
 * HSE master: Risk Priority Order (portal-synced, read-only).
 *
 * Global / non-tenant master fed by the HSE Portal three-step flow
 * (Get All Data From HSE → Refresh Master Data Info → Replace Master Data),
 * inheriting all of that scaffolding from {@see BaseHseMasterController}. This
 * concrete controller only supplies the per-master contract: model/table identity,
 * portal endpoint + staging mirror, the portal→staging and staging→master column
 * projections, and the per-master audit-column override.
 *
 * Natural-key master: the primary key `risk_acceptability_name` is a varchar
 * business key, so the row carries no DB-generated identity default —
 * {@see usesManualIdentityKey()} keeps its base default (false) and is NOT
 * overridden. {@see masterKeyColumn()} is the natural key `risk_acceptability_name`.
 *
 * ── ENDPOINT CORRECTION ──────────────────────────────────────────────────────
 * The portal endpoint is `MasterData/risk-acceptabilities` (VERIFIED). The earlier
 * guess `MasterData/risk-priority-orders` 404s and must NOT be used.
 *
 * Portal field list (VERIFIED, camelCase):
 *   id, minValue, maxValue, riskLevel, riskAcceptability, actionDescription,
 *   isActive, createdDate, modifiedDate.
 *
 * ── Column reconciliation ────────────────────────────────────────────────────
 * The verified portal shape differs substantially from the staging mirror
 * `m_master_risk_priority_order` (risk_acceptability_name, risk_order,
 * risk_range_lower, risk_range_upper). The two projections bridge the gap:
 *   - {@see mapPortalItem()} projects portal → staging:
 *       riskAcceptability → risk_acceptability_name
 *       minValue          → risk_range_lower
 *       maxValue          → risk_range_upper
 *       risk_order        ← IMPLEMENTATION DECISION (see below)
 *     Portal `actionDescription` has NO mirror column and is therefore DROPPED on
 *     sync (the mirror cannot carry it); `id`, `isActive`, `createdDate`,
 *     `modifiedDate` are likewise not mirrored.
 *   - {@see stagingToMasterMap()} is the identity projection — the mirror and the
 *     master share the four business column names.
 *
 * ── risk_order source (IMPLEMENTATION DECISION) ──────────────────────────────
 * The mirror's `risk_order` is a numeric ordinal, but the portal exposes no field
 * literally named "order". The portal's `riskLevel` is the natural ordinal source;
 * however it may be delivered as a non-numeric label (e.g. "High"). We therefore
 * derive risk_order as: use `riskLevel` when it is numeric, otherwise fall back to
 * the portal `id` (always an integer ordinal). This keeps risk_order populated and
 * ordered for every portal row regardless of how `riskLevel` is encoded.
 *
 * Audit columns: the master uses risk_*-prefixed audit columns rather than the base
 * default created_by/created_date/..., so {@see addAuditFields()} is overridden.
 *
 * _Requirements: 1.1, 1.2, 2.2, 4.1_
 */
class RiskPriorityOrderController extends BaseHseMasterController
{
    protected function modelClass(): string
    {
        return RiskPriorityOrder::class;
    }

    protected function tableName(): string
    {
        return 'm_risk_priority_order';
    }

    protected function stagingTable(): string
    {
        return 'm_master_risk_priority_order';
    }

    protected function resourceName(): string
    {
        return 'Risk Priority Order';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.risk_priority_order';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.risk_priority_order';
    }

    protected function hseEndpoint(): string
    {
        return 'MasterData/risk-acceptabilities';
    }

    /** Natural/business key (varchar) used for dedupe + ordering. */
    protected function masterKeyColumn(): string
    {
        return 'risk_acceptability_name';
    }

    protected function datatableColumns(): array
    {
        return [
            'risk_acceptability_name' => 'Risk Acceptability',
            'risk_order' => 'Order',
            'risk_range_lower' => 'Range Lower',
            'risk_range_upper' => 'Range Upper',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['risk_acceptability_name', 'risk_order', 'risk_range_lower', 'risk_range_upper'];
    }

    /**
     * Map one portal JSON item (VERIFIED fields) to a staging row.
     *
     * riskAcceptability→risk_acceptability_name, minValue→risk_range_lower,
     * maxValue→risk_range_upper. `risk_order` is derived from `riskLevel` when
     * numeric, else from the integer `id` (see class docblock). Portal
     * `actionDescription` is DROPPED — the mirror has no column for it.
     * All keys null-coalesce so a missing portal field stages as NULL.
     */
    protected function mapPortalItem(array $item): array
    {
        $riskLevel = $item['riskLevel'] ?? null;

        // risk_order: prefer a numeric riskLevel ordinal; otherwise fall back to the
        // portal id (always an integer). Non-numeric labels (e.g. "High") never leak
        // into the numeric risk_order column.
        $riskOrder = is_numeric($riskLevel)
            ? $riskLevel
            : ($item['id'] ?? null);

        return [
            'risk_acceptability_name' => $item['riskAcceptability'] ?? null,
            'risk_order' => $riskOrder,
            'risk_range_lower' => $item['minValue'] ?? null,
            'risk_range_upper' => $item['maxValue'] ?? null,
        ];
    }

    /**
     * Staging → master column projection ([staging_col => master_col]).
     *
     * Identity projection — the mirror and the master share the four business
     * column names.
     */
    protected function stagingToMasterMap(): array
    {
        return [
            'risk_acceptability_name' => 'risk_acceptability_name',
            'risk_order' => 'risk_order',
            'risk_range_lower' => 'risk_range_lower',
            'risk_range_upper' => 'risk_range_upper',
        ];
    }

    /**
     * Inject the master's risk_*-prefixed audit columns (not the base
     * created_by/created_date/updated_by/updated_date) before insert during Replace.
     */
    protected function addAuditFields(array $row): array
    {
        $now = now();
        $user = $this->userName();

        $row['risk_created_timestamp'] = $row['risk_created_timestamp'] ?? $now;
        $row['risk_created_by'] = $row['risk_created_by'] ?? $user;
        $row['risk_updated_timestamp'] = $now;
        $row['risk_updated_by'] = $user;

        return $row;
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $name = trim($row['risk_acceptability_name'] ?? '');
        if ($name === '') {
            return null;
        }

        return [
            'risk_acceptability_name' => $name,
            'risk_order' => trim($row['risk_order'] ?? '') === '' ? null : (int) $row['risk_order'],
            'risk_range_lower' => trim($row['risk_range_lower'] ?? '') === '' ? null : (int) $row['risk_range_lower'],
            'risk_range_upper' => trim($row['risk_range_upper'] ?? '') === '' ? null : (int) $row['risk_range_upper'],
        ];
    }

    protected function validateRow(array $row): ?string
    {
        return empty($row['risk_acceptability_name']) ? 'Risk Acceptability is required.' : null;
    }
}
