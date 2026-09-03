<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durian Activity master (global — no company_id, matches the other durian
 * lookup tables like fertilizer/pesticide/disease/soil_condition).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('m_durian_activity')) {
            Schema::create('m_durian_activity', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('activity_code')->nullable();
                $t->string('activity_group_code')->nullable();
                $t->string('activity_name')->nullable();
                $t->string('created_by')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('updated_by')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_durian_activity');
    }
};
