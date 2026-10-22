<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the estate-db-fusion working ledger table `fusion_ledger`.
 *
 * Spec authority: .kiro/specs/estate-db-fusion/design.md (FusionLedger in
 * "Data Models" + LLD-9) and requirements.md Requirement 14 (Idempotent Re-run).
 *
 * This is the ONLY new persisted schema the fusion subsystem introduces. It is a
 * single flexible table with an `entry_type` discriminator rather than several
 * narrow tables, because every ledger role shares the same handful of fields
 * (a key, a value/target id, an optional company scope, an optional free-text
 * message) and is read by `(entry_type, key_text)` lookups. One table keeps the
 * id-map, batch-status, and error/notes areas in one place so a stage can
 * resolve any of them with a single query and so the whole ledger can be cleared
 * or inspected as a unit. The alternative (separate tables per role) would
 * duplicate the same columns and indexes five times for no query benefit.
 *
 * entry_type values (R14 + LLD-9):
 *   batch            - per (source_db, table_name, batch_fingerprint) status so a
 *                      re-run skips an already-loaded batch (R14.1).
 *   user_dedup       - DedupKey -> target_user_id map; makes fusion:migrate-rbac a
 *                      no-op for an existing user (R14.2). key_text = dedup key,
 *                      value_text = target tc_user.id.
 *   scope_applied    - (user_id, scope_type, scope_id) -> applied marker so an
 *                      estate/country scope is not re-inserted on re-run (R14.2).
 *                      key_text = "user_id|scope_type|scope_id", value_text = '1'.
 *   attendance_nk    - attendance natural key (company_id, employee_code,
 *                      attendance_date) -> target t_attendance.id for referrers
 *                      (R12.8 / LLD-8). key_text = "employee_code|attendance_date"
 *                      (company in company_id), value_text = target id.
 *   tenant_master_id - tenant-master (company_id, business_key) -> new identity so
 *                      a re-run does not duplicate and children can resolve the
 *                      fresh id (R14.3 / R11.6). key_text = business key,
 *                      value_text = new target id.
 *   error            - a skipped row / unmapped value / type-coercion failure
 *                      (error ledger, R6.2 / R13.5).
 *   note             - a non-fatal conflict/divergence note (e.g. password or role
 *                      mismatch across source DBs, R8.4 / R8.5 / R2.4).
 *
 * Column type mapping to EPMS SQL Server conventions:
 *   bigIncrements('id')  -> BIGINT IDENTITY PRIMARY KEY
 *   string(n)            -> NVARCHAR(n)
 *   text('message')      -> NVARCHAR(MAX)
 *   timestamps()         -> DATETIME2 created_at / updated_at (nullable)
 */
return new class extends Migration
{
    /**
     * Run outside a transaction. On the ODBC SQL Server connection, DDL
     * (CREATE TABLE) implicitly commits, so wrapping the migration in a
     * transaction leaves no active transaction for the error handler's
     * ROLLBACK and raises SQLSTATE 25000 (3903) that masks the real result.
     * This mirrors the HSE migrations (2026_09_17_*) in this repo.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('fusion_ledger')) {
            return;
        }

        Schema::create('fusion_ledger', function (Blueprint $table) {
            // BIGINT IDENTITY primary key.
            $table->bigIncrements('id');

            // Discriminator: which ledger role this row plays.
            // batch | user_dedup | scope_applied | attendance_nk |
            // tenant_master_id | error | note
            $table->string('entry_type', 40);

            // Batch provenance / fingerprint area (R14.1).
            $table->string('source_db', 64)->nullable();
            $table->string('table_name', 128)->nullable();
            $table->string('batch_fingerprint', 128)->nullable();
            $table->string('status', 20)->nullable();

            // Generic key -> value id-map store shared by all map entry types
            // (user_dedup, scope_applied, attendance_nk, tenant_master_id).
            $table->string('key_text', 400)->nullable();
            $table->string('value_text', 400)->nullable();

            // Optional tenant scope for company-scoped keys (attendance natural
            // key, tenant-master key).
            $table->unsignedBigInteger('company_id')->nullable();

            // Error / notes ledger area (skips, conflicts, divergences) -
            // NVARCHAR(MAX).
            $table->text('message')->nullable();

            // DATETIME2 audit timestamps (nullable).
            $table->timestamps();

            // Primary lookup: resolve any map/batch entry by its discriminator
            // and key (dedup key, scope key, attendance key, business key).
            $table->index(['entry_type', 'key_text'], 'idx_fusion_ledger_type_key');

            // Batch idempotency lookup: skip an already-loaded batch.
            $table->index(
                ['source_db', 'table_name', 'batch_fingerprint'],
                'idx_fusion_ledger_batch'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fusion_ledger');
    }
};
