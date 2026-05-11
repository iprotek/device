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
    Route::get('/list', [DeviceAccessController::class, 'list'])
        ->defaults("_description", "List of devices")
        ->defaults("_is_visible", true)
        ->defaults("_is_allow", false)
        ->name('.list');

    Route::get('list-selection', [DeviceAccessController::class, 'list_selection'])
        ->defaults("_description", "List selection of device")
        ->defaults("_is_visible", true)
        ->defaults("_is_allow", false)
        ->name('.list-selection');

    //GET
    Route::get('get', [DeviceAccessController::class, 'get'])
        ->defaults("_description", "Get a device info")
        ->defaults("_is_visible", true)
        ->defaults("_is_allow", false)
        ->name('.get');

    //ADD
    Route::post('add', [DeviceAccessController::class, 'add'])
        ->defaults("_description", "Add a device")
        ->defaults("_is_visible", true)
        ->defaults("_is_allow", false)
        ->name('.add');

    //UPDATE
    Route::post('save', [DeviceAccessController::class, 'save'])
        ->defaults("_description", "Save a device")
        ->defaults("_is_visible", false)
        ->defaults("_is_allow", false)
        ->name('.save');

    //DELETE
    Route::post('delete', [DeviceAccessController::class, 'remove'])
        ->defaults("_description", "Remove a device")
        ->defaults("_is_visible", false)
        ->defaults("_is_allow", false)
        ->name('.remove');

    Route::get('logs', [DeviceAccessTriggerLogController::class, 'list'])
        ->defaults("_description", "Device trigger logs")
        ->defaults("_is_visible", false)
        ->defaults("_is_allow", true)
        ->name('.logs');

    Route::get('dynamic-selection', [DeviceAccessController::class, 'dynamic_selection'])
        ->defaults("_description", "Table dynamic selection for device trigger")
        ->defaults("_is_visible", true)
        ->defaults("_is_allow", false)
        ->name('.dynamic-selection');

    Route::get('target-table-info', [DeviceAccessController::class, 'target_table_info'])
        ->defaults("_description", "Target table info for device trigger")
        ->defaults("_is_visible", true)
        ->defaults("_is_allow", false)
        ->name('.target-table-info');

    Route::post('mikrotik-check-script', [DeviceAccessController::class, 'mikrotik_check_script'])
        ->defaults("_description", "Check the script of mikrotik")
        ->defaults("_is_visible", false)
        ->defaults("_is_allow", true)
        ->name('.mikrotk-check-script');

    Route::post('mikrotik-run-script', [DeviceAccessController::class, 'mikrotik_run_script'])
        ->defaults("_description", "Run the script of mikrotik")
        ->defaults("_is_visible", false)
        ->defaults("_is_allow", true)
        ->name('.mikrotk-run-script');

    Route::post('mikrotik-preview-script', [DeviceAccessController::class, 'mikrotik_preview_script'])
        ->defaults("_description", "Preview the script of mikrotik")
        ->defaults("_is_visible", false)
        ->defaults("_is_allow", true)
        ->name('.mikrotk-preview-script');

    Route::prefix('accounts')->name('.accounts')->group(function(){

        //LIST
        Route::get('list-device-triggers', [DeviceAccountController::class, 'list_device_triggers'])
            ->defaults("_description", "List device triggers for an account")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.list-device-triggers');

        //GET ONE
        Route::get('get-one', [DeviceAccountController::class, 'get_one'])
            ->defaults("_description", "Get a specific device trigger for an account")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.get-one');

        //REGISTER
        Route::post('register-account', [DeviceAccountController::class, 'register_account'])
            ->defaults("_description", "Implement registration for a device account")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.register');
        Route::post('register-account-preview', [DeviceAccountController::class, 'register_account_preview'])
            ->defaults("_description", "Preview registration for a device account")
            ->defaults("_is_visible", false)
            ->defaults("_is_allow", true)
            ->name('.register-preview');


        //UPDATE AUTO TRIGGER
        Route::put('update-auto-trigger', [DeviceAccountController::class, 'update_auto_trigger'])
            ->defaults("_description", "Update auto trigger for a device account")
            ->defaults("_is_visible", false)
            ->defaults("_is_allow", true)
            ->name('.update-auto-trigger');

        //UPDATE
        Route::put('update-account', [DeviceAccountController::class, 'update_account'])
            ->defaults("_description", "Update a device account")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.update-account');
        Route::post('update-account-preview', [DeviceAccountController::class, 'update_account_preview'])
            ->defaults("_description", "Preview update for a device account")
            ->defaults("_is_visible", false)
            ->defaults("_is_allow", true)
            ->name('.update-account-preview');

        //INACTIVE
        Route::put('set-inactive', [DeviceAccountController::class, 'set_inactive_account'])
            ->defaults("_description", "Set a device account as inactive")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.set-inactive');
        Route::post('set-inactive-preview', [DeviceAccountController::class, 'set_inactive_account_preview'])
            ->defaults("_description", "Preview setting a device account as inactive")
            ->defaults("_is_visible", false)
            ->defaults("_is_allow", true)
            ->name('.set-inactive-preview');

        //ACTIVE
        Route::put('set-active', [DeviceAccountController::class, 'set_active_account'])
            ->defaults("_description", "Set a device account as active")
            ->defaults("_is_visible", false)
            ->defaults("_is_allow", true)
            ->name('.set-active');
        Route::post('set-active-preview', [DeviceAccountController::class, 'set_active_account_preview'])
            ->defaults("_description", "Preview setting a device account as active")
            ->defaults("_is_visible", false)
            ->defaults("_is_allow", true)
            ->name('.set-active-preview');

        //REMOVE
        Route::delete('remove', [DeviceAccountController::class, 'remove_account'])
            ->defaults("_description", "Remove a device account")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.remove');
        Route::post('remove-preview', [DeviceAccountController::class, 'remove_account_preview'])
            ->defaults("_description", "Preview removal of a device account")
            ->defaults("_is_visible", false)
            ->defaults("_is_allow", true)
            ->name('.remove-preview');
    });

    //DEVICE TRIIGERS
    Route::prefix('trigger')->name('.trigger')->group(function(){
        
        //LIST
        Route::get('list', [DeviceTemplateTriggerController::class, 'list'])
            ->defaults("_description", "Get response from sms sender")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.list');
        Route::get('get-one', [DeviceTemplateTriggerController::class, 'get_one'])
            ->defaults("_description", "Get a single device template trigger")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.get-one');

        //ADD
        Route::post('add', [DeviceTemplateTriggerController::class, 'add'])
            ->defaults("_description", "Add a new device template trigger")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.add');

        //REMOVE
        Route::delete('remove/{trigger_device_id}', [DeviceTemplateTriggerController::class, 'remove'])
            ->defaults("_description", "Remove a device template trigger")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.remove');

        //UPDATE
        Route::put('update', [DeviceTemplateTriggerController::class, 'update'])
            ->defaults("_description", "Update a device template trigger")
            ->defaults("_is_visible", true)
            ->defaults("_is_allow", false)
            ->name('.update');

        Route::prefix('log')->name('.log')->group(function(){

            Route::get('list', [DeviceAccessTriggerLogController::class, 'list'])
                ->defaults("_description", "Get all logs of trigger and device")
                ->defaults("_is_visible", false)
                ->defaults("_is_allow", true)
                ->name('.list');

            Route::put('resolve', [DeviceAccessTriggerLogController::class, 'resolve'])
                ->defaults("_description", "Mark resolve to an error in device and trigger.")
                ->defaults("_is_visible", false)
                ->defaults("_is_allow", true)
                ->name('.resolve');

        });

    });


});