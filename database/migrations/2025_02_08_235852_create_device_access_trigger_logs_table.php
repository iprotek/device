<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeviceAccessTriggerLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('device_access_trigger_logs', function (Blueprint $table) {
            $table->id();
            $table->string('target_name');
            $table->integer('target_id');
            $table->integer('device_access_id');
            $table->longText('command');
            $table->longText('response');
            $table->longText('log_info');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_access_trigger_logs');
    }
}
