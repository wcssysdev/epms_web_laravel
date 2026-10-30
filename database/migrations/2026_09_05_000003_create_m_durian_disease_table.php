<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('m_durian_disease')) {
            Schema::create('m_durian_disease', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('disease_code')->nullable();
                $t->string('disease_desc')->nullable();
                $t->string('created_by')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('updated_by')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_durian_disease');
    }
};
