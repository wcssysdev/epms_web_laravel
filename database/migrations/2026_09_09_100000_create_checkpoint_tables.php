<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create t_checkpoint tables for Coconut CP functionality.
 * Structure mirrored from CI3 DB (sagil_mei).
 * 
 * t_checkpoint → main checkpoint record (CP Coconut)
 * t_checkpoint_detail → harvesting chit details linked to checkpoint
 * t_checkpoint_loader → loader/transporter assignments
 */
return new class extends Migration
{
    public function up(): void
    {
        // Main checkpoint table
        Schema::create('t_checkpoint', function (Blueprint $table) {
            $table->string('cp_id', 255)->primary();
            $table->string('cp_estate_code', 255)->nullable();
            $table->string('cp_division_code', 255)->nullable();
            $table->string('cp_seal_code', 255)->nullable();
            $table->string('cp_receiving_point_code', 255)->nullable();
            $table->string('cp_delivery_note', 255)->nullable();
            $table->string('cp_lat', 255)->default('0');
            $table->string('cp_long', 255)->default('0');
            $table->string('cp_photo', 255)->nullable();
            $table->integer('cp_total_nuts')->nullable();
            $table->integer('cp_total_hc')->nullable();
            $table->double('cp_bruto')->nullable();
            $table->double('cp_tarra')->nullable();
            $table->double('cp_estimate_tonnage')->nullable();
            $table->double('cp_actual_tonnage')->nullable();
            $table->smallInteger('cp_is_closed')->default(0);
            $table->string('cp_kerani_kirim_employee_code', 255)->nullable();
            $table->string('cp_kerani_kirim_employee_name', 255)->nullable();
            $table->smallInteger('cp_is_deleted')->default(0);
            $table->smallInteger('cp_closing_is_approved')->default(0);
            $table->string('cp_closing_is_approved_by')->nullable();
            $table->timestamp('cp_closing_approved_timestamp')->nullable();
            $table->smallInteger('cp_transporter')->nullable();
            $table->string('cp_license_number', 255)->nullable();
            $table->text('cp_vendor_code')->nullable();
            $table->text('cp_vendor_name')->nullable();
            $table->string('cp_license_number_vendor', 255)->nullable();
            $table->smallInteger('cp_transporter2')->nullable();
            $table->string('cp_license_number2', 255)->nullable();
            $table->string('cp_license_number_vendor2', 255)->nullable();
            $table->smallInteger('adjustment_status')->nullable();
            $table->integer('cp_bin_number')->nullable();
            $table->smallInteger('cp_type')->nullable()->comment('1=Palm, 2=Coconut');
            $table->date('cp_sailing_date')->nullable();
            $table->smallInteger('cp_ship_flag')->nullable();
            $table->smallInteger('cp_cable_way')->nullable();
            $table->smallInteger('integration_status')->default(-1);
            $table->string('request_id')->nullable();
            $table->string('remark')->nullable();
            $table->string('cp_created_by', 255)->nullable();
            $table->date('cp_created_date')->nullable();
            $table->time('cp_created_time')->nullable();
            $table->string('cp_updated_by', 255)->nullable();
            $table->date('cp_updated_date')->nullable();
            $table->time('cp_updated_time')->nullable();
            
            $table->index(['cp_estate_code', 'cp_created_date']);
            $table->index('cp_type');
            $table->index('integration_status');
        });

        // Checkpoint detail table
        Schema::create('t_checkpoint_detail', function (Blueprint $table) {
            $table->id('cp_detail_id');
            $table->string('cp_id', 255);
            $table->string('cp_hc_id', 255)->comment('Harvesting chit ID');
            $table->string('cp_hc_block_code', 255);
            $table->string('cp_hc_tph_code', 255);
            $table->string('cp_hc_card_id', 255)->nullable();
            $table->integer('cp_hc_nuts_delivered');
            $table->smallInteger('cp_detail_type')->nullable();
            $table->string('remark', 255)->nullable();
            $table->smallInteger('integration_status')->default(-1);
            
            $table->index('cp_id');
            $table->index('cp_hc_id');
        });

        // Checkpoint loader table
        Schema::create('t_checkpoint_loader', function (Blueprint $table) {
            $table->id('cp_loader_id');
            $table->string('cp_id', 255);
            $table->string('cp_loader_employee_code', 255)->nullable();
            $table->string('cp_loader_employee_name', 255);
            $table->string('cp_loader_vendor', 255)->nullable();
            $table->smallInteger('cp_loader_transporter')->nullable();
            $table->double('cp_loader_percentage');
            $table->smallInteger('cp_loader_type')->comment('1=Driver, 2=Loader, etc.');
            $table->smallInteger('integration_status')->default(-1);
            $table->string('request_id')->nullable();
            
            $table->index('cp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_checkpoint_loader');
        Schema::dropIfExists('t_checkpoint_detail');
        Schema::dropIfExists('t_checkpoint');
    }
};
