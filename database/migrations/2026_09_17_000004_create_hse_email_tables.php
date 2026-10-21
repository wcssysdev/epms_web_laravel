<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the two HSE email master tables: m_hse_email_receiver and
 * m_hse_email_template.
 *
 * Schema authority: C:\xampp\htdocs\epms24\CR_HSE_2026.txt (PostgreSQL DDL).
 *
 *   m_hse_email_receiver  PK id (sequence default m_hse_email_receiver_id_seq)
 *   m_hse_email_template  PK id (sequence default m_hse_email_template_id_seq),
 *                         UNIQUE template_code
 *
 * Unlike the manual-id portal masters, these use a real auto-increment/sequence
 * id. Both are global / non-tenant tables: NO company_id column. They carry the
 * DDL's four create/update audit columns (no deleted_*).
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
        // Table: m_hse_email_receiver (PK id sequence default)
        if (! Schema::hasTable('m_hse_email_receiver')) {
            Schema::create('m_hse_email_receiver', function (Blueprint $table) {
                $table->increments('id');
                $table->string('receiver_name', 100);
                $table->string('receiver_email', 150);
                $table->string('receiver_level', 50);
                $table->string('receiver_department', 100);
                $table->string('receiver_notification_type', 100)->default('All');
                $table->integer('receiver_is_active')->default(1);
                $table->integer('created_by')->nullable();
                $table->timestamp('created_date')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamp('updated_date')->nullable();
            });
        }

        // Table: m_hse_email_template (PK id sequence default, UNIQUE template_code)
        if (! Schema::hasTable('m_hse_email_template')) {
            Schema::create('m_hse_email_template', function (Blueprint $table) {
                $table->increments('id');
                $table->string('template_code', 50);
                $table->string('template_name', 100);
                $table->string('template_subject', 255);
                $table->text('template_body');
                $table->integer('template_is_active')->default(1);
                $table->integer('created_by')->nullable();
                $table->timestamp('created_date')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamp('updated_date')->nullable();

                $table->unique('template_code', 'm_hse_email_template_template_code_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_hse_email_template');
        Schema::dropIfExists('m_hse_email_receiver');
    }
};
