<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_adjustment — SAP Closing adjustment audit log.
 * Records every manual adjustment/reopen action done via the Closing SAP screens.
 * CI3 source: closing/Attendance.php etc. → $this->db->insert('t_adjustment', $save)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_adjustment', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->string('global_id')->nullable()->index();   // transaction record ID
            $table->text('note')->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->string('employee')->nullable();             // employee code/name of affected record
            $table->string('adjustment_type')->nullable();      // e.g. 'Attendance','OPH','Workdone', etc.
            $table->date('transaction_date')->nullable();
            $table->string('ajust_by')->nullable();             // user who performed adjustment
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_adjustment');
    }
};
