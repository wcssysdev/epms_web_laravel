<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EPMS Mobile API (v1_1) — BATCH 2b schema gap fill (field_staff upload).
 *
 * The mobile app uploads workdone fields the redesigned Laravel schema dropped.
 * Verified against api v1_1/In.php + the CI3 "tph" t_workdone / t_workdone_material.
 * All additive/nullable, unused by the web UI.
 *
 * Added:
 *   t_workdone:          block_status (smallint 0), wbs_name (string)
 *   t_workdone_material: material_uom (string)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_workdone', function (Blueprint $table) {
            if (! Schema::hasColumn('t_workdone', 'block_status')) {
                $table->smallInteger('block_status')->default(0);
            }
            if (! Schema::hasColumn('t_workdone', 'wbs_name')) {
                $table->string('wbs_name', 255)->nullable();
            }
        });

        Schema::table('t_workdone_material', function (Blueprint $table) {
            if (! Schema::hasColumn('t_workdone_material', 'material_uom')) {
                $table->string('material_uom', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_workdone', function (Blueprint $table) {
            foreach (['block_status', 'wbs_name'] as $col) {
                if (Schema::hasColumn('t_workdone', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('t_workdone_material', function (Blueprint $table) {
            if (Schema::hasColumn('t_workdone_material', 'material_uom')) {
                $table->dropColumn('material_uom');
            }
        });
    }
};
