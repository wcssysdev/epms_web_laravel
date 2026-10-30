<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('m_durian_variety')) {
            Schema::create('m_durian_variety', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('company_id')->nullable()->index();
                $t->string('estate_code')->nullable();
                $t->string('division_code')->nullable();
                $t->string('block_code')->nullable();
                $t->integer('row_no')->nullable();
                $t->string('variety')->nullable();
                $t->string('created_by')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('updated_by')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_durian_variety');
    }
};
