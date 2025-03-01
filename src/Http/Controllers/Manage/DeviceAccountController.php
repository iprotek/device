<?php

namespace iProtek\Device\Http\Controllers\Manage;

use Illuminate\Http\Request;
use iProtek\Core\Http\Controllers\_Common\_CommonController;
use iProtek\Core\Helpers\PayModelHelper;

use iProtek\Device\Models\DeviceTemplateTrigger;
use iProtek\Device\Models\DeviceAccess;
use iProtek\Device\Models\DeviceAccount;
use iProtek\Device\Helpers\DeviceHelper;

class DeviceAccountController extends _CommonController
{
    //

    public function list_device_triggers(Request $request){

        //Required
        //branch_id
        //target_name
        //target_id

        //TODO: branch_id and existing account
        $deviceAccessIds = DeviceAccess::whereRaw(' JSON_CONTAINS( branch_ids, ? ) ', [$request->branch_id])->get()->pluck('id')->toArray();

        //DeviceTemplateTrigger
        $list = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->where('is_active', true);
        $list->whereIn('device_access_id', $deviceAccessIds );
        $list->where('target_name', $request->target_name);
        $list->with(['device_access','device_accounts'=>function($q)use($request){
            $q->where('target_id', $request->target_id);
            $q->limit(1);
        }]);

        return $list->get();

    }

    public function get_one(Request $request){

    }

    public function register_account(Request $request){ 

        //TODO:: device_template_trigger_id
        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"required"
        ]);


        //CHECK IF ACCOUNT EXISTS
        $deviceAccount = PayModelHelper::get(DeviceAccount::class, $request)->where([
            "target_id"=>$request->target_id,
            "target_name"=>$request->target_name,
            "device_template_trigger_id"=>$request->device_template_trigger_id
        ])->first();

        if($deviceAccount){
            return ["status"=>0,"message"=>"Account already exists"];
        }


        //GET TEMPLATE TRIGGER INFO
        $trigger = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'])->where('is_active', true)->first();

        if(!$trigger || $trigger->is_active !== true ){
            return ["status"=>0,"message"=>"Device Trigger not available."];
        }

        //GET DEVICE
        $device_access = $trigger->device_access;
        if(!$device_access || $device_access->is_active !== true){
            return ["status"=>0,"message"=>"Device Access is not available."];
        }

        //CHECK IF ALLOW REGISTER
        if($trigger->enable_register !== true){
            return ["status"=>0, "message"=>"Register is disabled."];
        }

        //CHECK DEVICE
        //ADD DEVICE LOG

        //GET TEMPLATE TRANSLATE
        //$trigger->register_command_template
        
        $template = $trigger->register_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);
        if(is_array($translate) && $translate["status"] == 0){
            return $translate;
        }
        if( !is_string( $translate)){
            return ["status"=>0, "message"=>"Invalid Command "];
        }
        return ["status"=>0, "message"=>$translate];

        //IF MIKROTIK
        if($device_access->type == 'mikrotik'){
            return \iProtek\Device\Helpers\Console\MikrotikHelper::register(
                $request,
                $trigger,
                $translate,
                $request->target_name,
                $request->target_id
            );
        }
        else{

        }


        //ELSE IF SSH


        //ELSE IF WINDOWS



        //ADD TO LOG

        //CHECK DEVICE CONNECTION


        //ADD TO LOG
        //EXECUTE THE COMMAND


      
        //RENDER THE ID

        //ADD DEVICE ACCOUNT

        return ["status"=>0, "message"=>"Device Registration Not available in this type." ];
 

    }
    public function register_account_preview(Request $request){
        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"nullable",
            //"template"=>"required"
        ]);

        $template = $request->template ?? ""; 
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);


        return ["status"=>1, "message"=>"Translate Complete", "template"=>$template, "template_translate"=>$translate];

    }

    public function update_account(Request $request){

    }
    public function update_account_preview(Request $request){
        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"nullable",
            //"template"=>"required"
        ]);

        $template = $request->template ?? ""; 
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);


        return ["status"=>1, "message"=>"Translate Complete", "template"=>$template, "template_translate"=>$translate];

    }

    
    public function set_active_account(Request $request){ 

        //TODO:: device_template_trigger_id

        //check account if existed


    }    
    public function set_active_account_preview(Request $request){ 

        
        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"nullable",
            //"template"=>"required"
        ]);

        $template = $request->template ?? ""; 
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);


        return ["status"=>1, "message"=>"Translate Complete", "template"=>$template, "template_translate"=>$translate];

    }

    public function set_inactive_account(Request $request){

        //TODO:: device_template_trigger_id

        //check account if existed


    }
    public function set_inactive_account_preview(Request $request){

        
        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"nullable",
            //"template"=>"required"
        ]);

        $template = $request->template ?? ""; 
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);


        return ["status"=>1, "message"=>"Translate Complete", "template"=>$template, "template_translate"=>$translate];

    }
    
    public function remove_account(Request $request){

        //TODO:: device_template_trigger_id



    }
    public function remove_account_preview(Request $request){

       
        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"nullable",
            //"template"=>"required"
        ]);

        $template = $request->template ?? ""; 
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);


        return ["status"=>1, "message"=>"Translate Complete", "template"=>$template, "template_translate"=>$translate];

    }


}
