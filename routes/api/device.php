<?php

use Illuminate\Support\Facades\Route;  
//use iProtek\Core\Http\Controllers\Manage\FileUploadController; 
//use iProtek\Core\Http\Controllers\Manage\CmsController;
//use App\Http\Controllers\Manage\BillingSharedAccountDefaultBranchController;
use iProtek\Device\Http\Controllers\Manage\DeviceAccessController;
use iProtek\Device\Http\Controllers\Manage\DeviceAccessTriggerLogController;
use iProtek\Device\Http\Controllers\Manage\DeviceTemplateTriggerController;
use iProtek\Device\Http\Controllers\Manage\DeviceAccountController;
use Illuminate\Http\Request; 

 
Route::prefix('/devices')->name('.devices')->group(function(){
    
    //LIST & GET
    Route::get('/list', [
        "uses"=>[DeviceAccessController::class, 'list'],
        "description"=>"List of devices",
        "is_visible"=>true,
        "is_allow"=>false
    ])->name('.list');

    Route::get('list-selection', [
        "uses"=>[DeviceAccessController::class, 'list_selection'],
        "description"=>"List selection of device",
        "is_visible"=>true,
        "is_allow"=>false
    ])->name('.list-selection');

    //GET
    Route::get('get', [
        "uses"=>[DeviceAccessController::class, 'get'],
        "description"=>"Get a device info",
        "is_visible"=>true,
        "is_allow"=>false
    ])->name('.get');

    //ADD
    Route::post('add', [
        "uses"=>[DeviceAccessController::class, 'add'],
        "description"=>"Add a device",
        "is_visible"=>true,
        "is_allow"=>false
    ])->name('.add');

    //UPDATE
    Route::post('save', [
        "uses"=>[DeviceAccessController::class, 'save'],
        "description"=>"Save a device",
        "is_visible"=>false,
        "is_allow"=>false
    ])->name('.save');

    //DELETE
    Route::post('delete', [
        "uses"=>[DeviceAccessController::class, 'remove'],
        "description"=>"Remove a device",
        "is_visible"=>false,
        "is_allow"=>false
    ])->name('.remove');

    Route::get('logs', [
        "uses"=>[DeviceAccessTriggerLogController::class, 'list'],
        "description"=>"Device trigger logs",
        "is_visible"=>false,
        "is_allow"=>true
    ])->name('.logs');

    Route::get('dynamic-selection', [
        "uses"=>[DeviceAccessController::class, 'dynamic_selection'],
        "description"=>"Table dynamic selection for device trigger",
        "is_visible"=>true,
        "is_allow"=>false
    ])->name('.dynamic-selection');

    Route::get('target-table-info', [
        "uses"=>[DeviceAccessController::class, 'target_table_info'],
        "description"=>"Target table info for device trigger",
        "is_visible"=>true,
        "is_allow"=>false
    ])->name('.target-table-info');

    Route::prefix('accounts')->name('.accounts')->group(function(){

        //LIST
        Route::get('list-device-triggers', [
            "uses"=>[DeviceAccountController::class, 'list_device_triggers'],
            "description"=>"List device triggers for an account",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.list-device-triggers');

        //GET ONE
        Route::get('get-one', [
            "uses"=>[DeviceAccountController::class, 'get_one'],
            "description"=>"Get a specific device trigger for an account",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.get-one');

        //REGISTER
        Route::post('register-account', [
            "uses"=>[DeviceAccountController::class, 'register_account'],
            "description"=>"Implement registration for a device account",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.register');
        Route::post('register-account-preview', [
            "uses"=>[DeviceAccountController::class, 'register_account_preview'],
            "description"=>"Preview registration for a device account",
            "is_visible"=>false,
            "is_allow"=>true
        ])->name('.register-preview');


        //UPDATE AUTO TRIGGER
        Route::put('update-auto-trigger', [
            "uses"=>[DeviceAccountController::class, 'update_auto_trigger'],
            "description"=>"Update auto trigger for a device account",
            "is_visible"=>false,
            "is_allow"=>true
        ])->name('.update-auto-trigger');

        //UPDATE
        Route::put('update-account', [
            "uses"=>[DeviceAccountController::class, 'update_account'],
            "description"=>"Update a device account",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.update-account');
        Route::post('update-account-preview', [
            "uses"=>[DeviceAccountController::class, 'update_account_preview'],
            "description"=>"Preview update for a device account",
            "is_visible"=>false,
            "is_allow"=>true
        ])->name('.update-account-preview');

        //INACTIVE
        Route::put('set-inactive', [
            "uses"=>[DeviceAccountController::class, 'set_inactive_account'],
            "description"=>"Set a device account as inactive",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.set-inactive');
        Route::post('set-inactive-preview', [
            "uses"=>[DeviceAccountController::class, 'set_inactive_account_preview'],
            "description"=>"Preview setting a device account as inactive",
            "is_visible"=>false,
            "is_allow"=>true
        ])->name('.set-inactive-preview');

        //ACTIVE
        Route::put('set-active', [
            "uses"=>[DeviceAccountController::class, 'set_active_account'],
            "description"=>"Set a device account as active",
            "is_visible"=>false,
            "is_allow"=>true
        ])->name('.set-active');
        Route::post('set-active-preview', [
            "uses"=>[DeviceAccountController::class, 'set_active_account_preview'],
            "description"=>"Preview setting a device account as active",
            "is_visible"=>false,
            "is_allow"=>true
        ])->name('.set-active-preview');

        //REMOVE
        Route::delete('remove', [
            "uses"=>[DeviceAccountController::class, 'remove_account'],
            "description"=>"Remove a device account",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.remove');
        Route::post('remove-preview', [
            "uses"=>[DeviceAccountController::class, 'remove_account_preview'],
            "description"=>"Preview removal of a device account",
            "is_visible"=>false,
            "is_allow"=>true
        ])->name('.remove-preview');



    });

    //DEVICE TRIIGERS
    Route::prefix('trigger')->name('.trigger')->group(function(){
        
        //LIST
        Route::get('list', [
            "uses"=>[DeviceTemplateTriggerController::class, 'list'],
            "description"=>"Get response from sms sender",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.list');
        Route::get('get-one', [
            "uses"=>[DeviceTemplateTriggerController::class, 'get_one'],
            "description"=>"Get a single device template trigger",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.get-one');

        //ADD
        Route::post('add', [
            "uses"=>[DeviceTemplateTriggerController::class, 'add'],
            "description"=>"Add a new device template trigger",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.add');

        //REMOVE
        Route::delete('remove/{trigger_device_id}', [
            "uses"=>[DeviceTemplateTriggerController::class, 'remove'],
            "description"=>"Remove a device template trigger",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.remove');

        //UPDATE
        Route::put('update', [
            "uses"=>[DeviceTemplateTriggerController::class, 'update'],
            "description"=>"Update a device template trigger",
            "is_visible"=>true,
            "is_allow"=>false
        ])->name('.update');


    });

});