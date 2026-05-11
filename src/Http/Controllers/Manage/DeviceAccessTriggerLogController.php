<?php

namespace iProtek\Device\Http\Controllers\Manage;

use Illuminate\Http\Request;
use iProtek\Core\Http\Controllers\_Common\_CommonController;
use iProtek\Core\Helpers\PayModelHelper;

use iProtek\Device\Models\DeviceAccess;
use iProtek\Device\Helpers\Console\MikrotikHelper;
use iProtek\Device\Helpers\Console\SshHelper;
use iProtek\Device\Models\DeviceAccessTriggerLog;
use Illuminate\Support\Facades\Log; 

class DeviceAccessTriggerLogController extends _CommonController
{
    //
    public $guard = "admin";
    //
    public function list(Request $request){

        //$data = $this->validate($request, [
        //    "device_access_id"=>"required"
        //])->validated(); 

        $data = [];
        if($request->device_access_id){
            $data["device_access_id"] = $request->device_access_id;
        }

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

    public function resolve(Request $request){
        $this->validate($request, [
            "log_id"=>"nullable",
            "device_access_id"=>"nullable",
            "target_name"=>"required",
            "target_id"=>"required",
            "resolved_info"=>"required",
            "device_template_trigger_id"=>"nullable"
        ]);

        $logs = DeviceAccessTriggerLog::on();
        //Specific log id
        if($request->log_id){
            $logs->where('id', $request->log_id);
        }
        //Specific trigger
        if($request->device_template_trigger_id){
            $logs->where('device_template_trigger_id', $request->device_template_trigger_id);
        }
        
        //Required
        if($request->device_access_id){
            $logs->where('device_access_id', $request->device_access_id);
        }

        $logs->where([
            "target_name"=>$request->target_name,
            "target_id"=>$request->target_id,
            "is_resolved"=>false,
            "status_id"=>2
        ]);
        
        PayModelHelper::update($logs, $request, [ 
            "is_resolved"=>true,
            "resolved_info"=>$request->resolved_info
        ]);
        

        return ["status"=>1, "message"=>"Done updating."];
    }
}
