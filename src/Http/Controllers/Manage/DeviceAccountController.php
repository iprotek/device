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

    public function update_auto_trigger(Request $request){
        $this->validate($request, [
            "device_account_id"=>"required",
            "is_auto_trigger"=>"required"
        ]);

        $deviceAccount = PayModelHelper::get(DeviceAccount::class, $request)->where('id', $request->device_account_id);
        
        if(!$deviceAccount){
            return ["status"=>0, "message"=>"Permission Denied"];
        }


        PayModelHelper::update($deviceAccount, $request, [
            "is_auto_trigger"=>$request->is_auto_trigger
        ]);

        return ["status"=>1, "message"=>"Updated"];

    }

    public function register_account(Request $request){ 

        //TODO:: device_template_trigger_id
        $requestData = $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"required"
        ])->validated();


        //CHECK IF ACCOUNT EXISTS
        $deviceAccount = PayModelHelper::get(DeviceAccount::class, $request)->where($requestData)->first();

        if($deviceAccount){
            return ["status"=>0,"message"=>"Account already exists"];
        }

        //GET TEMPLATE TRIGGER INFO
        $trigger = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'])->where('is_active', true)->find($requestData['device_template_trigger_id']);

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
        
        $template = $trigger->register_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $requestData['target_name'], $requestData['target_id']);
        if(is_array($translate) && $translate["status"] == 0){
            return $translate;
        }
        if( !is_string( $translate)){
            return ["status"=>0, "message"=>"Invalid Command "];
        }
        //return ["status"=>0, "message"=>$translate];

        //IF MIKROTIK
        if($device_access->type == 'mikrotik'){
            return \iProtek\Device\Helpers\Console\MikrotikHelper::register(
                $request,
                $trigger,
                $translate,
                $requestData['target_name'],
                $requestData['target_id']
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

        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"required",
            "device_account_id"=>"required"
        ]);

        
        //GET TEMPLATE TRIGGER INFO
        $trigger = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'])->where('is_active', true)->find($request->device_template_trigger_id);
        $device_access = $trigger->device_access;
        if(!$trigger || $trigger->is_active !== true ){
            return ["status"=>0,"message"=>"Device Trigger not available."];
        }

        $device_account = PayModelHelper::get(DeviceAccount::class, $request)->find($request->device_account_id);
        if(!$device_account){
            return ["status"=>0, "message"=>"Permission Denied"];
        }

        $template = $trigger->update_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);
        if(is_array($translate) && $translate["status"] == 0){
            return $translate;
        }
        if( !is_string( $translate)){
            return ["status"=>0, "message"=>"Invalid Command "];
        }

        
        if($device_access->type == 'mikrotik'){
            return \iProtek\Device\Helpers\Console\MikrotikHelper::update(
                $device_account, 
                $translate,
                $request->target_name,
                $request->target_id,
                $request
            );
        }
        else{

        }


        return ["status"=>0, "message"=>"Update Error. Device Not Found."]; 
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

        $requestedData = $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"required",
            "device_account_id"=>"required"
        ])->validated();

        
        //GET TEMPLATE TRIGGER INFO
        $trigger = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'])->where('is_active', true)->find($requestedData['device_template_trigger_id']);//$request->device_template_trigger_id);
        $device_access = $trigger->device_access;

        if(!$trigger || $trigger->is_active !== true ){
            return ["status"=>0,"message"=>"Device Trigger not available."];
        }

        $device_account = PayModelHelper::get(DeviceAccount::class, $request)->find($requestedData['device_account_id']);//$request->device_account_id);
        if(!$device_account){
            return ["status"=>0, "message"=>"Permission Denied"];
        }

        $template = $trigger->active_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id'] );// $request->target_name, $request->target_id);
        if(is_array($translate) && $translate["status"] == 0){
            return $translate;
        }
        if( !is_string( $translate)){
            return ["status"=>0, "message"=>"Invalid Command "];
        }

        
        if($device_access->type == 'mikrotik'){
            return \iProtek\Device\Helpers\Console\MikrotikHelper::active(
                $device_account, 
                $translate,
                $requestedData['target_name'],
                $requestData['target_id'],
                $request
            );
        }
        else{

        }



        return ["status"=>1, "message"=>"Successfully Set Active."]; 

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
        
        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"required",
            "device_account_id"=>"required"
        ]);

        
        //GET TEMPLATE TRIGGER INFO
        $trigger = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'])->where('is_active', true)->find($request->device_template_trigger_id);
        $device_access = $trigger->device_access;

        if(!$trigger || $trigger->is_active !== true ){
            return ["status"=>0,"message"=>"Device Trigger not available."];
        }

        $device_account = PayModelHelper::get(DeviceAccount::class, $request)->find($request->device_account_id);
        if(!$device_account){
            return ["status"=>0, "message"=>"Permission Denied"];
        }

        $template = $trigger->inactive_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);
        if(is_array($translate) && $translate["status"] == 0){
            return $translate;
        }
        if( !is_string( $translate)){
            return ["status"=>0, "message"=>"Invalid Command "];
        }

        
        if($device_access->type == 'mikrotik'){
            return \iProtek\Device\Helpers\Console\MikrotikHelper::inactive(
                $device_account, 
                $translate,
                $request->target_name,
                $request->target_id,
                $request
            );
        }
        else{

        }

        return ["status"=>1, "message"=>"Successfully Set Inactive."]; 

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

        $this->validate($request, [
            "target_id"=>"required",
            "target_name"=>"required",
            "device_template_trigger_id"=>"required",
            "device_account_id"=>"required"
        ]);


        
        
        //GET TEMPLATE TRIGGER INFO
        $trigger = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'])->where('is_active', true)->find($request->device_template_trigger_id);
        $device_access = $trigger->device_access;

        if(!$trigger || $trigger->is_active !== true ){
            return ["status"=>0,"message"=>"Device Trigger not available."];
        }

        $device_account = PayModelHelper::get(DeviceAccount::class, $request)->find($request->device_account_id);
        if(!$device_account){
            return ["status"=>0, "message"=>"Permission Denied"];
        }

        $template = $trigger->remove_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $request->target_name, $request->target_id);
        if(is_array($translate) && $translate["status"] == 0){
            return $translate;
        }
        if( !is_string( $translate)){
            return ["status"=>0, "message"=>"Invalid Command "];
        }


        
        if($device_access->type == 'mikrotik'){
            return \iProtek\Device\Helpers\Console\MikrotikHelper::remove(
                $device_account, 
                $translate,
                $request->target_name,
                $request->target_id,
                $request
            );
        }
        else{

        }
        return ["status"=>1, "message"=>"Successfully Removed"]; 

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
