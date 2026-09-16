<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add closing_is_approved + closing_approved_by + closing_approved_at
 * to tr_gi_header and tr_gr_header for Closing SAP and SAP Closing Approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tr_gi_header', 'tr_gr_header'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                if (! Schema::hasColumn($t->getTable(), 'closing_is_approved')) {
                    $t->smallInteger('closing_is_approved')->default(0);
                }
                if (! Schema::hasColumn($t->getTable(), 'closing_approved_by')) {
                    $t->string('closing_approved_by')->nullable();
                }
                if (! Schema::hasColumn($t->getTable(), 'closing_approved_at')) {
                    $t->timestamp('closing_approved_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['tr_gi_header', 'tr_gr_header'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                foreach (['closing_is_approved', 'closing_approved_by', 'closing_approved_at'] as $col) {
                    if (Schema::hasColumn($t->getTable(), $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};