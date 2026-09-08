<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EPMS Mobile API (v1_1) — BATCH 2a schema gap fill.
 *
 * The legacy CI3 mobile API (DB "tph") stores fields on t_oph and t_fdn that the
 * mobile app uploads but which were dropped/simplified when the Laravel ("epms_l")
 * schema was redesigned around the CI4 web app. To keep mobile uploads lossless
 * and the JSON contract intact, we re-add exactly the columns the mobile app sends
 * (verified against api/application/controllers/v1_1/In.php + the tph schema).
 *
 * All columns are nullable/default 0 and are NOT used by the existing web UI,
 * so this is additive and safe for the current app.
 *
 * Added:
 *   t_oph: oph_deduction_indicator, bunches_pest_damaged_old, bunches_pest_damaged_new
 *   t_fdn: 14 fdn_bunches_* grading columns + fdn_write_off, fdn_line_number, company_code
 *
 * Intentionally NOT added (verified NOT sent by mobile / unset in In.php):
 *   oph_is_revised, oph_harvesting_deduction_is_locked, loose_fruits_is_in_bag,
 *   loose_fruits_is_dirty, fdn_bunches_total_old, fdn_write_off_qty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_oph', function (Blueprint $table) {
            if (! Schema::hasColumn('t_oph', 'oph_deduction_indicator')) {
                $table->smallInteger('oph_deduction_indicator')->default(0);
            }
            if (! Schema::hasColumn('t_oph', 'bunches_pest_damaged_old')) {
                $table->integer('bunches_pest_damaged_old')->default(0);
            }
            if (! Schema::hasColumn('t_oph', 'bunches_pest_damaged_new')) {
                $table->integer('bunches_pest_damaged_new')->default(0);
            }
        });

        Schema::table('t_fdn', function (Blueprint $table) {
            $bunches = [
                'fdn_bunches_wet', 'fdn_bunches_ripe', 'fdn_bunches_overripe',
                'fdn_bunches_underripe', 'fdn_bunches_unripe', 'fdn_bunches_rotten',
                'fdn_bunches_long_stalk', 'fdn_bunches_empty', 'fdn_bunches_dirty',
                'fdn_bunches_unfresh', 'fdn_bunches_old', 'fdn_bunches_pest_damaged_old',
                'fdn_bunches_pest_damaged_new', 'fdn_bunches_diseased',
            ];
            foreach ($bunches as $col) {
                if (! Schema::hasColumn('t_fdn', $col)) {
                    $table->integer($col)->default(0);
                }
            }
            if (! Schema::hasColumn('t_fdn', 'fdn_write_off')) {
                $table->decimal('fdn_write_off', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('t_fdn', 'fdn_line_number')) {
                $table->integer('fdn_line_number')->default(0);
            }
            if (! Schema::hasColumn('t_fdn', 'company_code')) {
                $table->string('company_code', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_oph', function (Blueprint $table) {
            foreach (['oph_deduction_indicator', 'bunches_pest_damaged_old', 'bunches_pest_damaged_new'] as $col) {
                if (Schema::hasColumn('t_oph', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('t_fdn', function (Blueprint $table) {
            $cols = [
                'fdn_bunches_wet', 'fdn_bunches_ripe', 'fdn_bunches_overripe',
                'fdn_bunches_underripe', 'fdn_bunches_unripe', 'fdn_bunches_rotten',
                'fdn_bunches_long_stalk', 'fdn_bunches_empty', 'fdn_bunches_dirty',
                'fdn_bunches_unfresh', 'fdn_bunches_old', 'fdn_bunches_pest_damaged_old',
                'fdn_bunches_pest_damaged_new', 'fdn_bunches_diseased',
                'fdn_write_off', 'fdn_line_number', 'company_code',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('t_fdn', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
