<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAccessTriggerLogsStatus extends Migration
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
            
            $table->bigInteger('group_id')->nullable();
            $table->bigInteger('pay_created_by')->nullable();
            $table->bigInteger('pay_updated_by')->nullable();
            $table->bigInteger('pay_deleted_by')->nullable();

            $table->integer('status_id')->default(0); //0 -pending, 1-success, 2-
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
