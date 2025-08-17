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
    Route::get('/list', [DeviceAccessController::class, 'list'])->name('.list');

    Route::get('list-selection', [DeviceAccessController::class, 'list_selection'])->name('.list-selection');

    //GET
    Route::get('get', [DeviceAccessController::class, 'get'])->name('.get');

    //ADD
    Route::post('add', [DeviceAccessController::class, 'add'])->name('.add');

    //UPDATE
    Route::post('save', [DeviceAccessController::class, 'save'])->name('.save');

    //DELETE
    Route::post('delete', [DeviceAccessController::class, 'remove'])->name('.remove');

    Route::get('logs', [DeviceAccessTriggerLogController::class, 'list'])->name('.logs');

    Route::get('dynamic-selection', [DeviceAccessController::class, 'dynamic_selection'])->name('.dynamic-selection');

    Route::prefix('accounts')->name('.accounts')->group(function(){

        //LIST
        Route::get('list-device-triggers', [DeviceAccountController::class, 'list_device_triggers'])->name('.list-device-triggers');

        //GET ONE
        Route::get('get-one', [DeviceAccountController::class, 'get_one'])->name('.get-one');

        //REGISTER
        Route::post('register-account', [DeviceAccountController::class, 'register_account'])->name('.register');
        Route::post('register-account-preview', [DeviceAccountController::class, 'register_account_preview'])->name('.register-preview');


        //UPDATE AUTO TRIGGER
        Route::put('update-auto-trigger', [DeviceAccountController::class, 'update_auto_trigger'])->name('.update-auto-trigger');

        //UPDATE
        Route::put('update-account', [DeviceAccountController::class, 'update_account'])->name('.update-account');
        Route::post('update-account-preview', [DeviceAccountController::class, 'update_account_preview'])->name('.update-account-preview');

        //INACTIVE
        Route::put('set-inactive', [DeviceAccountController::class, 'set_inactive_account'])->name('.set-inactive');
        Route::post('set-inactive-preview', [DeviceAccountController::class, 'set_inactive_account_preview'])->name('.set-inactive-preview');

        //ACTIVE
        Route::put('set-active', [DeviceAccountController::class, 'set_active_account'])->name('.set-active');
        Route::post('set-active-preview', [DeviceAccountController::class, 'set_active_account_preview'])->name('.set-active-preview');

        //REMOVE
        Route::delete('remove', [DeviceAccountController::class, 'remove_account'])->name('.remove');
        Route::post('remove-preview', [DeviceAccountController::class, 'remove_account_preview'])->name('.remove-preview');



    });

    //DEVICE TRIIGERS
    Route::prefix('trigger')->name('.trigger')->group(function(){
        
        //LIST
        Route::get('list', [DeviceTemplateTriggerController::class, 'list'])->name('.list');
        //LIST
        Route::get('get-one', [DeviceTemplateTriggerController::class, 'get_one'])->name('.get-one');

        //ADD
        Route::post('add', [DeviceTemplateTriggerController::class, 'add'])->name('.add');

        //REMOVE
        Route::delete('remove/{trigger_device_id}', [DeviceTemplateTriggerController::class, 'remove'])->name('.remove');

        //UPDATE
        Route::put('update', [DeviceTemplateTriggerController::class, 'update'])->name('.update');


    });

});