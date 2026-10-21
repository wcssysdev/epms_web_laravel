<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the m_incident_time master table.
 *
 * Schema authority: C:\xampp\htdocs\epms24\CR_HSE_2026.txt (PostgreSQL DDL).
 *
 *   m_incident_time  PK incident_time_name (varchar 100)
 *
 * This master has no HSE Portal endpoint, so it is managed via plain CRUD + CSV
 * (no portal sync). It carries only the DDL's four create/update audit columns
 * (no deleted_*), and is a global / non-tenant table: NO company_id column.
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
        // Table: m_incident_time (PK incident_time_name varchar(100))
        if (! Schema::hasTable('m_incident_time')) {
            Schema::create('m_incident_time', function (Blueprint $table) {
                $table->string('incident_time_name', 100)->primary();
                $table->timestamp('incident_time_created_timestamp')->nullable();
                $table->string('incident_time_created_by', 20)->nullable();
                $table->timestamp('incident_time_updated_timestamp')->nullable();
                $table->string('incident_time_updated_by', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_incident_time');
    }
};
