<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeviceTemplateTriggersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('device_template_triggers', function (Blueprint $table) {

            $table->id();
            
            $table->bigInteger('group_id'); 
            $table->bigInteger('pay_created_by')->nullable(); 
            $table->bigInteger('pay_updated_by')->nullable();
            $table->bigInteger('pay_deleted_by')->nullable();
 
            $table->string('trigger_name');
            $table->string('target_name');
            $table->integer('target_id')->default(0); //0 means all
            $table->integer('device_access_id');

            //ACCOUNT REGISTRATION
            $table->boolean('enable_register')->default(0);
            $table->text('register_command_template')->nullable();

            //ACCOUNT CHANGES
            $table->boolean('enable_update')->default(0);
            $table->text('update_command_template')->nullable();

            //ACCOUTN INACTIVE/DISABLE
            $table->boolean('enable_inactive')->default(0);
            $table->text('inactive_command_template')->nullable(); 


            //ACCOUTN  ACTIVE/ENABLE
            $table->boolean('enable_active')->default(0);
            $table->text('active_command_template')->nullable(); 

            //ACCOUTN  DELETE/REMOVE
            $table->boolean('enable_remove')->default(0);
            $table->text('remove_command_template')->nullable();  
            
            $table->boolean('is_active')->default(0);
            $table->string('inactive_reason')->nullable();
            
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
        Schema::dropIfExists('device_template_triggers');
    }
}
