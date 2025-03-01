<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeviceAccessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('device_accesses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('group_id')->nullable();
            $table->bigInteger('pay_created_by')->nullable(); 
            $table->bigInteger('pay_updated_by')->nullable();
            $table->bigInteger('pay_deleted_by')->nullable(); 
            $table->softDeletes();
            $table->timestamps();

            $table->string('type'); //microtik/windows/ssh
            $table->string('name');
            $table->longText('description')->nullable();
            $table->string('host');
            $table->string('user');
            $table->string('password');
            $table->integer('port')->default(0);
            $table->string('branch_ids',255)->default('[]');
            $table->string('branch_source')->default('branches');
            $table->boolean('is_app_execute')->default(0);
            $table->boolean('is_active')->default(0);
            $table->boolean('is_error')->default(0);
            $table->longText('error_info')->nullable();


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_accesses');
    }
}
