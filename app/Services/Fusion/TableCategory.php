<?php

namespace App\Services\Fusion;

/**
 * The eight table categories a source table is classified into by
 * {@see TableTaxonomy}, per design LLD-3.
 *
 * NOTE (task 2.1 reconciliation): this enum is a deliverable of task 2.1
 * (SourceDescriptor / TablePlan / TenantRef / PkStrategy / TableCategory).
 * At the time task 2.2 (TableTaxonomy) was implemented only
 * app/Services/Fusion/SourceConnection.php existed, so this enum was created
 * here as the minimal fallback the taxonomy depends on. When task 2.1 lands,
 * reconcile by keeping ONE definition of this enum (prefer task 2.1's if it is
 * identical) — the cases below match the design's
 * `category ∈ { EXCLUDE, GLOBAL_MASTER, TENANT_MASTER, VARCHAR_ID_HEADER,
 * BIGINT_ID_CHILD, ATTENDANCE_SPECIAL, RBAC, TENANT_META }`.
 */
enum TableCategory: string
{
    /** res_data, audit_trail — never read. */
    case EXCLUDE = 'EXCLUDE';

    /** Target table has NO company_id — deduped across all 12 DBs by natural key. */
    case GLOBAL_MASTER = 'GLOBAL_MASTER';

    /** m_* table with company_id in the target — stamped and re-identitied. */
    case TENANT_MASTER = 'TENANT_MASTER';

    /** App-generated VARCHAR id transaction header — id carried verbatim. */
    case VARCHAR_ID_HEADER = 'VARCHAR_ID_HEADER';

    /** bigint-id detail/child transaction table — id dropped, business FK kept. */
    case BIGINT_ID_CHILD = 'BIGINT_ID_CHILD';

    /** t_attendance — bigint id dropped, natural key recorded in the ledger. */
    case ATTENDANCE_SPECIAL = 'ATTENDANCE_SPECIAL';

    /** tc_user / tc_user_access / tc_user_scope / m_roles — handled by RbacMigrator. */
    case RBAC = 'RBAC';

    /** m_config / m_company* / m_country / m_estate — handled by TenantRoleSeeder. */
    case TENANT_META = 'TENANT_META';
}
