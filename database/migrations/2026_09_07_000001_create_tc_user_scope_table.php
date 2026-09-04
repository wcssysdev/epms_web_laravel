<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-scope assignment table for roles that span more than one
 * estate or country (e.g. Plantation Controller = multiple estates
 * in one company, or a country-admin that covers several countries).
 *
 * Each row grants a user access to ONE scope target. A user may have
 * many rows. scope_type discriminates the target:
 *   - 'country' => scope_id references m_country.id
 *   - 'estate'  => scope_id references m_estate.id
 *
 * The single-scope columns on tc_user_access (company_id/country_id)
 * remain the primary/home scope; this table is additive for multi-scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tc_user_scope')) {
            return;
        }

        Schema::create('tc_user_scope', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('scope_type', 20);        // 'country' | 'estate'
            $table->unsignedBigInteger('scope_id');   // m_country.id or m_estate.id
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 100)->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->index('user_id');
            $table->index(['scope_type', 'scope_id']);
            $table->unique(['user_id', 'scope_type', 'scope_id'], 'tc_user_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tc_user_scope');
    }
};
