<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the five natural-key (and sequence-keyed) portal-synced HSE master tables.
 *
 * Schema authority: C:\xampp\htdocs\epms24\CR_HSE_2026.txt (PostgreSQL DDL).
 *
 *   m_process_location    PK ploc_id (varchar)
 *   m_likelihood          PK lhood_id (identity), has is_active
 *   m_severity            PK id (sequence default m_severity_id_seq)
 *   m_risk_priority_order PK risk_acceptability_name (varchar)
 *   m_course              PK course_no_epms (varchar), incl. sync columns
 *
 * Global / non-tenant tables: NO company_id column on any table.
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
        // Table: m_process_location (PK ploc_id varchar)
        if (! Schema::hasTable('m_process_location')) {
            Schema::create('m_process_location', function (Blueprint $table) {
                $table->string('ploc_id', 20)->primary();
                $table->string('ploc_desc', 100)->nullable();
                $table->string('ploc_status', 20)->nullable();
                $table->string('ploc_rev_no', 2)->nullable();
                $table->timestamp('ploc_created_timestamp')->nullable();
                $table->string('ploc_created_by', 20)->nullable();
                $table->timestamp('ploc_updated_timestamp')->nullable();
                $table->string('ploc_updated_by', 20)->nullable();
            });
        }

        // Table: m_likelihood (PK lhood_id identity, has is_active)
        if (! Schema::hasTable('m_likelihood')) {
            Schema::create('m_likelihood', function (Blueprint $table) {
                $table->increments('lhood_id');
                $table->string('lhood_name', 50)->nullable();
                $table->text('lhood_description')->nullable();
                $table->string('lhood_factors', 100)->nullable();
                $table->text('lhood_justification')->nullable();
                $table->string('lhood_code', 10)->nullable();
                $table->decimal('lhood_rating', 2, 0)->nullable();
                $table->timestamp('lhood_created_timestamp')->nullable();
                $table->string('lhood_created_by', 20)->nullable();
                $table->timestamp('lhood_updated_timestamp')->nullable();
                $table->string('lhood_updated_by', 20)->nullable();
                $table->boolean('is_active')->default(true);
            });
        }

        // Table: m_severity (PK id with sequence default m_severity_id_seq)
        if (! Schema::hasTable('m_severity')) {
            Schema::create('m_severity', function (Blueprint $table) {
                $table->increments('id');
                $table->string('severity_code', 20)->nullable();
                $table->string('severity_name', 20);
                $table->decimal('severity_rating', 2, 0)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('severity_created_timestamp')->nullable();
                $table->string('severity_created_by', 20)->nullable();
                $table->timestamp('severity_updated_timestamp')->nullable();
                $table->string('severity_updated_by', 20)->nullable();
            });
        }

        // Table: m_risk_priority_order (PK risk_acceptability_name varchar)
        if (! Schema::hasTable('m_risk_priority_order')) {
            Schema::create('m_risk_priority_order', function (Blueprint $table) {
                $table->string('risk_acceptability_name', 20)->primary();
                $table->decimal('risk_order', 1, 0)->nullable();
                $table->decimal('risk_range_lower', 2, 0)->nullable();
                $table->decimal('risk_range_upper', 2, 0)->nullable();
                $table->timestamp('risk_created_timestamp')->nullable();
                $table->string('risk_created_by', 20)->nullable();
                $table->timestamp('risk_updated_timestamp')->nullable();
                $table->string('risk_updated_by', 20)->nullable();
            });
        }

        // Table: m_course (PK course_no_epms varchar, incl. sync columns)
        if (! Schema::hasTable('m_course')) {
            Schema::create('m_course', function (Blueprint $table) {
                $table->string('course_no_epms', 20)->primary();
                $table->string('course_no', 20)->nullable();
                $table->string('course_estate_code', 5)->nullable();
                $table->string('course_name', 20)->nullable();
                $table->string('course_desc', 255)->nullable();
                $table->string('course_category', 20)->nullable();
                $table->date('course_validity_from')->nullable();
                $table->date('course_validity_to')->nullable();
                $table->string('course_trainer_name', 20)->nullable();
                $table->text('course_contact_detail')->nullable();
                $table->decimal('course_duration', 3, 1)->nullable();
                $table->string('course_type', 20)->nullable();
                $table->decimal('course_is_approved', 1, 0)->nullable();
                $table->timestamp('course_approved_timestamp')->nullable();
                $table->string('course_approved_by', 20)->nullable();
                $table->string('course_approved_by_designation', 20)->nullable();
                $table->timestamp('course_created_timestamp')->nullable();
                $table->string('course_created_by', 20)->nullable();
                $table->timestamp('course_updated_timestamp')->nullable();
                $table->string('course_updated_by', 20)->nullable();
                $table->string('course_submitter_estate_code', 5)->nullable();
                $table->timestamp('course_submitted_timestamp')->nullable();
                $table->string('course_sync_status', 20)->default('pending');
                $table->timestamp('course_sync_timestamp')->nullable();
                $table->string('course_hse_portal_id', 50)->nullable();

                $table->index('course_no', 'idx_m_course_course_no');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_course');
        Schema::dropIfExists('m_risk_priority_order');
        Schema::dropIfExists('m_severity');
        Schema::dropIfExists('m_likelihood');
        Schema::dropIfExists('m_process_location');
    }
};
