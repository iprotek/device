<?php

namespace iProtek\Device\Http\Controllers\Manage;

use Illuminate\Http\Request;
use iProtek\Core\Http\Controllers\_Common\_CommonController;
use iProtek\Device\Models\DeviceAccess;
use iProtek\Core\Helpers\PayModelHelper;
use iProtek\Device\Helpers\Console\MikrotikHelper;
use iProtek\Device\Helpers\Console\SshHelper;
use iProtek\Device\Models\DeviceAccessTriggerLog;

class DeviceAccessTriggerLogController extends _CommonController
{
    //
    public $guard = "admin";
    //
    public function list(Request $request){

        $data = $this->validate($request, [
            "device_access_id"=>"required"
        ])->validated(); 

        $deviceList = PayModelHelper::get(DeviceAccessTriggerLog::class, $request, $data);
        
        if($request->target_name){
            $deviceList->where('target_name', $request->target_name);
        }

        if($request->target_id){
            $deviceList->where('target_id', $request->target_id);
            
        }


        if($request->search_text){
            $search_text = '%'.str_replace(' ', '%', $request->search_text).'%';
            $deviceList->whereRaw(' CONCAT(target_name, command, log_info) LIKE ?', [$search_text]); 
        } 

        return $deviceList->paginate(10);
    }
}
