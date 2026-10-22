<?php

namespace App\Services\Fusion;

/**
 * Primary-key strategy attached to a {@see TablePlan}, per design LLD-3 / LLD-8.
 *
 * NOTE (task 2.1 reconciliation): deliverable of task 2.1; created here as the
 * minimal fallback the taxonomy depends on (only SourceConnection.php existed
 * when task 2.2 was implemented). Reconcile to a single definition when 2.1
 * lands. The values below are the strategies the design names per category.
 */
enum PkStrategy: string
{
    /** No PK handling (EXCLUDE, and the handled-elsewhere RBAC/TENANT_META rows). */
    case NONE = 'NONE';

    /** GLOBAL master: upsert by natural/business key; let the target identity stand. */
    case UPSERT_NATURAL_KEY = 'UPSERT_NATURAL_KEY';

    /**
     * Drop the source id so SQL Server assigns a fresh identity
     * (TENANT_MASTER, BIGINT_ID_CHILD, ATTENDANCE_SPECIAL).
     */
    case DROP_REIDENTITY = 'DROP_REIDENTITY';

    /** VARCHAR_ID_HEADER: carry the app-generated VARCHAR id verbatim (globally unique). */
    case CARRY_VARCHAR_ID = 'CARRY_VARCHAR_ID';
}
