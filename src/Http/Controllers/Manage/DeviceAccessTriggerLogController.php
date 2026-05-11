<?php

namespace iProtek\Device\Http\Controllers\Manage;

use Illuminate\Http\Request;
use iProtek\Core\Http\Controllers\_Common\_CommonController;
use iProtek\Core\Helpers\PayModelHelper;

use iProtek\Device\Models\DeviceAccess;
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

        $deviceList = PayModelHelper::get(DeviceAccessTriggerLog::class, $request, $data)
        ->with(['trigger'=>function($q){
            $q->select('id', 'trigger_name', 'is_active', 'device_access_id');
            $q->with(['device_access'=>function($q){
                $q->select('id', 'name', 'is_active');
            }]);
        }]);
        
        if($request->target_name){
            $deviceList->where('target_name', $request->target_name);
        }

        if($request->target_id){
            $deviceList->where('target_id', $request->target_id);
        }

        if($request->device_template_trigger_id){
            $deviceList->where('device_template_trigger_id', $request->device_template_trigger_id);
        }

        if($request->search_text){
            $search_text = '%'.str_replace(' ', '%', $request->search_text).'%';
            $deviceList->whereRaw(' ( CONCAT(target_name, command, log_info) LIKE ? )', [$search_text]); 
        } 

        return $deviceList->orderBy('is_resolved','ASC')->orderBy('id', 'desc')->paginate(10);
    }
}
