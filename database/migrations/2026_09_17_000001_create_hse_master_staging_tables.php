<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HSE Portal master-data staging mirror tables (m_master_*).
 *
 * Data-only buffers for the HSE Portal three-step sync
 * (Get From HSE → Refresh Master Data Info → Replace Master Data).
 * Column names/types mirror the authoritative PostgreSQL DDL in
 * CR_HSE_2026.txt and intentionally use the portal-shaped names,
 * which differ from the live master tables (resolved per-controller
 * via stagingToMasterMap() during Replace).
 *
 * By design these mirrors carry ONLY the business columns:
 *   - NO audit columns (created_by/created_date/...)
 *   - NO identity / primary key
 *   - NO company_id (HSE masters are global/non-tenant)
 *
 * Flow:
 *   Step 1 "Get From HSE"       → truncate + insert into m_master_*
 *   Step 2 "Refresh Info"       → COUNT(master) vs COUNT(staging)
 *   Step 3 "Replace Master Data"→ map m_master_* → m_* master (tx + rollback)
 */
return new class extends Migration
{
    /**
     * Run outside a transaction. On the ODBC SQL Server connection, DDL
     * (CREATE TABLE) implicitly commits, so wrapping the migration in a
     * transaction leaves no active transaction for the error handler's
     * ROLLBACK and raises SQLSTATE 25000 (3903) that masks the real result.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // ── m_master_category_of_injuries ───────────────────────────────────
        if (! Schema::hasTable('m_master_category_of_injuries')) {
            Schema::create('m_master_category_of_injuries', function (Blueprint $t) {
                $t->string('category_injuries_code', 20)->nullable();
                $t->string('category_injuries', 100)->nullable();
                $t->string('injuries_type', 50)->nullable();
            });
        }

        // ── m_master_type_of_injury ─────────────────────────────────────────
        if (! Schema::hasTable('m_master_type_of_injury')) {
            Schema::create('m_master_type_of_injury', function (Blueprint $t) {
                $t->string('type_injury_code', 20)->nullable();
                $t->string('injury_name', 100)->nullable();
                $t->string('afr_code', 10)->nullable();
                $t->string('afr_jkkp_name', 255)->nullable();
                $t->string('benchmark_esg_name', 255)->nullable();
            });
        }

        // ── m_master_type_of_disease ────────────────────────────────────────
        if (! Schema::hasTable('m_master_type_of_disease')) {
            Schema::create('m_master_type_of_disease', function (Blueprint $t) {
                $t->string('disease_code', 20)->nullable();
                $t->string('disease_name', 255)->nullable();
                $t->string('disease_category', 255)->nullable();
                $t->string('afr_code', 10)->nullable();
                $t->string('afr_jkkp_name', 255)->nullable();
                $t->string('benchmark_name', 255)->nullable();
            });
        }

        // ── m_master_type_of_accident ───────────────────────────────────────
        if (! Schema::hasTable('m_master_type_of_accident')) {
            Schema::create('m_master_type_of_accident', function (Blueprint $t) {
                $t->string('accident_code', 20)->nullable();
                $t->string('accident_name', 255)->nullable();
                $t->string('afr_jkkp_name', 255)->nullable();
                $t->string('afr_code', 255)->nullable();
            });
        }

        // ── m_master_first_aid_kit ──────────────────────────────────────────
        if (! Schema::hasTable('m_master_first_aid_kit')) {
            Schema::create('m_master_first_aid_kit', function (Blueprint $t) {
                $t->string('kit_code', 20)->nullable();
                $t->string('kit_name', 255)->nullable();
                $t->string('uom', 10)->nullable();
            });
        }

        // ── m_master_process_location ───────────────────────────────────────
        if (! Schema::hasTable('m_master_process_location')) {
            Schema::create('m_master_process_location', function (Blueprint $t) {
                $t->text('process_location_code')->nullable();
                $t->text('process_location_name')->nullable();
                $t->text('description')->nullable();
            });
        }

        // ── m_master_likelihood ─────────────────────────────────────────────
        if (! Schema::hasTable('m_master_likelihood')) {
            Schema::create('m_master_likelihood', function (Blueprint $t) {
                $t->text('likelihood_code')->nullable();
                $t->text('likelihood_name')->nullable();
                $t->text('likelihood_rating')->nullable();
                $t->text('description')->nullable();
            });
        }

        // ── m_master_severity ───────────────────────────────────────────────
        if (! Schema::hasTable('m_master_severity')) {
            Schema::create('m_master_severity', function (Blueprint $t) {
                $t->text('severity_code')->nullable();
                $t->text('severity_name')->nullable();
                $t->text('description')->nullable();
                $t->text('severity_rating')->nullable();
            });
        }

        // ── m_master_risk_priority_order ────────────────────────────────────
        if (! Schema::hasTable('m_master_risk_priority_order')) {
            Schema::create('m_master_risk_priority_order', function (Blueprint $t) {
                $t->string('risk_acceptability_name', 50)->nullable();
                $t->decimal('risk_order', 5, 2)->nullable();
                $t->decimal('risk_range_lower', 10, 2)->nullable();
                $t->decimal('risk_range_upper', 10, 2)->nullable();
            });
        }

        // ── m_master_course ─────────────────────────────────────────────────
        if (! Schema::hasTable('m_master_course')) {
            Schema::create('m_master_course', function (Blueprint $t) {
                $t->string('course_code', 50)->nullable();
                $t->string('course_name', 255)->nullable();
                $t->string('course_category', 100)->nullable();
                $t->decimal('duration', 10, 2)->nullable();
                $t->string('duration_unit', 50)->nullable();
                $t->date('validity_from')->nullable();
                $t->date('validity_to')->nullable();
                $t->string('course_type', 50)->nullable();
                $t->string('trainer_name', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_master_category_of_injuries');
        Schema::dropIfExists('m_master_type_of_injury');
        Schema::dropIfExists('m_master_type_of_disease');
        Schema::dropIfExists('m_master_type_of_accident');
        Schema::dropIfExists('m_master_first_aid_kit');
        Schema::dropIfExists('m_master_process_location');
        Schema::dropIfExists('m_master_likelihood');
        Schema::dropIfExists('m_master_severity');
        Schema::dropIfExists('m_master_risk_priority_order');
        Schema::dropIfExists('m_master_course');
    }
};
