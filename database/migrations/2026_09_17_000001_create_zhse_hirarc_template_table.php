<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HIRARC template staging table (HSE Portal sync).
 *
 * Data-only staging mirror for the HSE Portal HIRARC template fetch. It holds
 * the raw portal payload before any apply-to-t_hirarc step (which is out of
 * scope for the Master HSE admin migration). There is no primary key and no
 * audit columns — the HirarcTemplateController truncates and re-inserts this
 * table on each "Get Template" fetch.
 *
 * The representatives/details payloads are preserved verbatim as PostgreSQL
 * jsonb snapshots.
 *
 * Schema authority: CR_HSE_2026.txt (HIRARC module schema baseline).
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
        if (Schema::hasTable('zhse_hirarc_template')) {
            return;
        }

        Schema::create('zhse_hirarc_template', function (Blueprint $table) {
            $table->string('hirarc_no', 50)->nullable();
            $table->string('hirarc_rev_no', 10)->nullable();
            $table->date('hirarc_rev_date')->nullable();
            $table->char('hirarc_ops_center_code', 20)->nullable();
            $table->char('hirarc_ploc_id', 20)->nullable();
            $table->string('hirarc_approved_by', 100)->nullable();
            $table->string('hirarc_approved_by_designation', 100)->nullable();
            $table->string('hirarc_remark', 50)->nullable();
            $table->string('portal_status', 50)->nullable();
            $table->jsonb('representatives_snapshot')->nullable();
            $table->jsonb('details_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zhse_hirarc_template');
    }
};
