<?php

use Illuminate\Support\Facades\Route;

include(__DIR__.'/api.php');

Route::middleware(['web'])->group(function(){
 
    Route::middleware(['auth'])->prefix('manage')->name('manage')->group(function(){ 
    }); 
});