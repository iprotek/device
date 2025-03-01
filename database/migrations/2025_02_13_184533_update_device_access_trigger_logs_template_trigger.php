<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDeviceAccessTriggerLogsTemplateTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('device_access_trigger_logs', function (Blueprint $table) {
            $table->integer('device_template_trigger_id')->default(0); //0 -pending, 1-success, 2-failed
            $table->string('device_template_trigger_action')->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
