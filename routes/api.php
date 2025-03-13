<?php

use Illuminate\Support\Facades\Route; 
use iProtek\Core\Http\Controllers\Manage\FileUploadController; 
use iProtek\Core\Http\Controllers\AppVariableController; 

//Route::prefix('sms-sender')->name('sms-sender')->group(function(){
  //  Route::get('/', [SmsController::class, 'index'])->name('.index');
//});
Route::prefix('api')->middleware('api')->name('api')->group(function(){ 

    //Route::prefix('message')->name('.message')->group(function(){

      Route::prefix('group/{group_id}')->middleware(['pay.api'])->group(function(){ 
          //FILE UPLOADS
          //include(__DIR__.'/api/file-upload.php');

          //FILE UPLOADS
          //include(__DIR__.'/api/meta-data.php'); 
          
          //Device
          include(__DIR__.'/api/device.php');
      
        });

    //});
}); 
