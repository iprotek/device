<?php

namespace iProtek\Device\Http\Controllers\Manage;

use Illuminate\Http\Request;
use iProtek\Core\Http\Controllers\_Common\_CommonController;
use iProtek\Device\Models\DeviceTemplateTrigger; 
use iProtek\Core\Helpers\PayModelHelper;

class DeviceTemplateTriggerController extends _CommonController
{
    protected $guard = "admin";
    //
    public function list(Request $request){

        $data = $this->validate($request,[
            "target_name"=>"required",
            "target_id"=>"required"
        ])->validated();

        $template = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'=>function($q){
            $q->select('id', 'name', 'type', \DB::raw(" CONCAT(name,' - [ ', type, ' ] ' ) as text"));
        }])->where($data);
        

        return $template->get();

    }

    public function get_one(Request $request){
        
        $data = $this->validate($request,[ 
            "id"=>"required"
        ])->validated();
        
        $template = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'=>function($q){
            $q->select('id', 'name', 'type', \DB::raw(" CONCAT(name,' - [ ', type, ' ] ' ) as text"));
        }])->where($data);

        return $template->first();

    }


    public function add(Request $request){
        $data = $this->validate($request, [
            "trigger_name"=>"required",
            "target_name"=>"required",
            "target_id"=>"required",
            "device_access_id"=>"required",
            "enable_register"=>"required",
            "register_command_template"=>"nullable",
            "enable_update"=>"required",
            "update_command_template"=>"nullable",
            "enable_inactive"=>"required",
            "inactive_command_template"=>"nullable",
            "enable_active"=>"required",
            "active_command_template"=>"nullable",
            "enable_remove"=>"required",
            "remove_command_template"=>"nullable",
            "is_active"=>"required",
            "inactive_reason"=>"nullable"
        ])->validated();


        //CHECK IF TRIGGER NAME EXISTS
        $exists = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->where('trigger_name', $request->trigger_name)->first();
        if($exists){
            return ["status"=>0, "message"=>"Trigger Name Already exists"];
        }

        $created = PayModelHelper::create(DeviceTemplateTrigger::class, $request, $data);


        return ["status"=>1, "message"=>"Successfully Added","data"=> $created];
    }

    public function update(Request $request){
        $data = $this->validate($request, [
            //"id"=>"required",
            "trigger_name"=>"required",
            "target_name"=>"required",
            "target_id"=>"required",
            "device_access_id"=>"required",
            "enable_register"=>"required",
            "register_command_template"=>"nullable",
            "enable_update"=>"required",
            "update_command_template"=>"nullable",
            "enable_inactive"=>"required",
            "inactive_command_template"=>"nullable",
            "enable_active"=>"required",
            "active_command_template"=>"nullable",
            "enable_remove"=>"required",
            "remove_command_template"=>"nullable",
            "is_active"=>"required"
        ])->validated();
        $template = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->find($request->id);

        if(!$template){
            return ["status"=>0, "message"=>"Permssion Denied"];
        }

        PayModelHelper::update($template, $request, $data);


        
        return ["status"=>1, "message"=>"Successfully Updated","data"=> $template];

    }

    public function remove(Request $request){
        $trigger_device_id = $request->trigger_device_id;
        $template = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->find($trigger_device_id);

        if(!$template){
            return ["status"=>0, "message"=>"Permssion Denied"];
        }

        PayModelHelper::delete($template, $request);

        return ["status"=>1, "message"=>"Successfully deleted."];
    }

}
