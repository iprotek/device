<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeviceAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('device_accounts', function (Blueprint $table) {
            $table->id();
            
            $table->bigInteger('group_id'); 
            $table->bigInteger('pay_created_by')->nullable(); 
            $table->bigInteger('pay_updated_by')->nullable();
            $table->bigInteger('pay_deleted_by')->nullable();
            $table->timestamps();

            $table->integer('device_template_trigger_id');
            $table->string('target_name');
            $table->integer('target_id');
            $table->boolean('is_active')->default(0);
            $table->string('account_id');
            $table->text('active_info')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_accounts');
    }
}
