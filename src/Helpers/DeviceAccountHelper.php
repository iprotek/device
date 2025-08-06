<?php
namespace iProtek\Device\Helpers;

use DB; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use iProtek\Device\Helpers\DeviceVariableHelper;
use iProtek\Device\Models\DeviceAccount;
use iProtek\Device\Models\DeviceTemplateTrigger;
use iProtek\Device\Models\DeviceAccessTriggerLog;
use iProtek\Device\Helpers\DeviceHelper;
use iProtek\Device\Helpers\Console\MikrotikHelper;
use Illuminate\Http\Request;
use iProtek\Device\Models\DeviceAccess;
use iProtek\Core\Helpers\PayModelHelper;

class DeviceAccountHelper {


    
    static function log( $target_name, $target_id, $device_access_id, $command, $response, $log_info, $status_id, $trigger_id){

        DeviceAccessTriggerLog::create([
            "target_name"=>$target_name,
            "target_id"=> $target_id,
            "device_access_id"=> $device_access_id,
            "command"=> $command,
            "response"=> $response,
            "log_info"=> $log_info,
            "status_id"=> $status_id,
            "device_template_trigger_id"=> $trigger_id
        ]);
    }


    //This should be placed when adding new entry
    public static function autoRegister(Request $request, $target_id, $target_name, $branch_id){

        //PREVENT ENTRIES FROM EMPTY REQUEST
        if($request === null)
            return;


        //checking for active device with enabled register
        $triggers = PayModelHelper::get(DeviceTemplateTrigger::class, $request, ["target_id"=>$target_id, "target_name"=>$target_name, "enable_register"=>true]);

        //Check if has trigger in specific branch
        $triggers->whereHas('device_access', function($q)use($branch_id){
            $q->where('is_trigger_registration', true);
            $q->whereRaw(" json_contains(branch_ids, '?')", $branch_id);
            $q->where('is_active', true);
        });


        //Execute triggers by Loop
        $triggerList = $triggers->get();


        foreach($triggerList as $trigger){
            static::register($request, $target_name, $target_id, $trigger->id);
        }

    }

    //REGISTER
    public static function register( Request $request, $target_name, $target_id, $device_template_trigger_id){

        //PREVENT ENTRIES FROM EMPTY REQUEST
        if($request === null)
            return;
        
        $requestedData = [
            "target_name"=>$target_name,
            "target_id"=>$target_id,
            "device_template_trigger_id"=>$device_template_trigger_id
        ];

        //CHECK IF ACCOUNT EXISTS
        $deviceAccount = PayModelHelper::get(DeviceAccount::class, $request)->where($requestedData)->first();

        if($deviceAccount){

            static::log( $target_name, $target_id, 0, "--", "Failed to register already exists.", "Failed to register already exists.", 2, $device_template_trigger_id);
            return ["status"=>0,"message"=>"Account already exists"];
        
        }

        //GET TEMPLATE TRIGGER INFO
        $trigger = PayModelHelper::get(DeviceTemplateTrigger::class, $request)->with(['device_access'])->where('is_active', true)->find($device_template_trigger_id);

        if(!$trigger || $trigger->is_active !== true ){

            static::log( $target_name, $target_id, 0, "--", "Device Trigger not available.", "Device Trigger not available.", 2, $device_template_trigger_id);
            return ["status"=>0,"message"=>"Device Trigger not available."];

        }

        //GET DEVICE
        $device_access = $trigger->device_access;
        if(!$device_access || $device_access->is_active !== true){

            static::log( $target_name, $target_id, 0, "--", "Device Access is not available.", "Device Access is not available.", 2, $device_template_trigger_id);
            return ["status"=>0,"message"=>"Device Access is not available."];

        }

        //CHECK IF ALLOW REGISTER
        if($trigger->enable_register !== true){
            
            static::log( $target_name, $target_id, 0, "--", "Register is disabled.", "Register is disabled.", 2, $device_template_trigger_id);
            return ["status"=>0, "message"=>"Register is disabled."];
        
        }
        
        $template = $trigger->register_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
        if(is_array($translate) && $translate["status"] == 0){

            //return ["status"=>0, "message"=>"Invalid".$requestedData['target_name'] . "-" . $requestedData['target_id']];
            static::log( $target_name, $target_id, 0, "--", "Translate error: ".$translate["message"], "Translate error: ".$translate["message"], 2, $device_template_trigger_id);
            return $translate;

        }
        if( !is_string( $translate)){
            static::log( $target_name, $target_id, 0, "--", "Translate error: Invalid Command", "Translate error: Invalid Command", 2, $device_template_trigger_id);
            return ["status"=>0, "message"=>"Invalid Command "];
        }
        //return ["status"=>0, "message"=>$translate];

        //IF MIKROTIK
        if($device_access->type == 'mikrotik'){
            return \iProtek\Device\Helpers\Console\MikrotikHelper::register(
                $request,
                $trigger,
                $translate,
                $requestedData['target_name'],
                $requestedData['target_id']
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

    //UPDATES
    public static function update($target_name, $target_id, $force=false){
        
        $current_device_trigger = null;
        try{

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::with(['device_access'])->where(['target_name'=> $target_name, 'enable_update'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log($target_name, $target_id, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                
                
                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id
                ];
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();

                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    static::log($target_name, $target_id, $trigger->device_access_id, "--", "Account not exist for activating.", "Account not exist for activating.", 2, $trigger->id);
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::log( $target_name, $target_id, $trigger->device_access_id, "--", "Device is inactive or not found!", "Device is inactive or not found.", 2, $trigger->id);
                    continue;
                }
                
                //GET TEMPLATE TRIGGER INFO
                
                $template = $trigger->update_command_template;
                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "failed to translate.", 2, $trigger->id);
                    continue;
                }
                if( !is_string( $translate)){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "Invalid translation.", 2, $trigger->id);
                    continue;
                }

                
                if($device_access->type == 'mikrotik'){
                    MikrotikHelper::update(
                        $deviceAccount, 
                        $translate,
                        $target_name,
                        $target_id
                    );
                    continue;
                }
                static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Update not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);

            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to activate:".$ex->getMessage()];

        }


        return ["status"=>1, "message"=>"Successfully Updated."];
    }   

    //ACTIVATE
    public static function active($target_name, $target_id, $force=false){

        $current_device_trigger = null;
        try{

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::with(['device_access'])->where(['target_name'=> $target_name, 'enable_active'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log( $target_name, 0, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                
                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id
                ];
                
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();

                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    static::log($target_name, $target_id, $trigger->device_access_id, "--", "Account not exist for activating.", "Account not exist for activating.", 2, $trigger->id);
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                

                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::log($target_name, $target_id, $trigger->device_access_id, "--", "Device is inactive or not found!", "Device is inactive or not found.", 2, $trigger->id);
                    continue;
                }

                $template = $trigger->active_command_template;

                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "failed to translate.", 2, $trigger->id);
                    continue;
                }
                if( !is_string( $translate)){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "Invalid translation.", 2, $trigger->id);
                    continue;
                }

                
                if($device_access->type == 'mikrotik'){
                    MikrotikHelper::active(
                        $deviceAccount, 
                        $translate,
                        $requestedData['target_name'],
                        $requestedData['target_id']
                    );
                    continue;
                }
                static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Registration not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);
              


            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to activate:".$ex->getMessage()];

        }


        return ["status"=>1, "message"=>"Successfully Activated."];
        
    }

    //INACTIVE
    public static function inactive($target_name, $target_id, $force=false){
        
        $current_device_trigger = null;
        try{

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::with(['device_access'])->where(['target_name'=> $target_name, 'enable_inactive'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log($target_name, $target_id, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                

                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id
                ];
                
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();


                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    static::log($target_name, $target_id, $trigger->device_access_id, "--", "Account not exist for deactivation.", "Account not exist for activating.", 2, $trigger->id);
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::log($target_name, $target_id, $trigger->device_access_id, "--", "Device is inactive or not found!", "Device is inactive or not found.", 2, $trigger->id);
                    continue;
                }

                //GET TEMPLATE TRIGGER INFO
                $template = $trigger->inactive_command_template;
                
                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "failed to translate.", 2, $trigger->id);
                    continue;
                }
                if( !is_string( $translate)){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "Invalid translation.", 2, $trigger->id);
                    continue;
                }

                
                if($device_access->type == 'mikrotik'){
                    MikrotikHelper::inactive(
                        $DeviceAccount, 
                        $translate,
                        $requestedData['target_name'],
                        $requestedData['target_id']
                    );
                    continue;
                }
                static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Update not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);

            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to inactive:".$ex->getMessage()];

        }



        return ["status"=>1, "message"=>"Successfully Inactive."];

    }

    //REMOVE
    public static function remove($target_name, $target_id, $force=false){


        $current_device_trigger = null;
        try{

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::with(['device_access'])->where(['target_name'=> $target_name, 'enable_remove'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log($target_name, $target_id, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id,
                ];
                
                
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();

                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    static::log($target_name, $target_id, $trigger->device_access_id, "--", "Account not exist for removal.", "Account not exist for activating.", 2, $trigger->id);
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::log($target_name, $target_id, $trigger->device_access_id, "--", "Device is inactive or not found!", "Device is inactive or not found.", 2, $trigger->id);
                    continue;
                }
                
                //GET TEMPLATE TRIGGER INFO
                $template = $trigger->remove_command_template;
                
                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "failed to translate.", 2, $trigger->id);
                    continue;
                }
                if( !is_string( $translate)){
                    static::log( $target_name, $target_id, $trigger->device_access_id, $template, "Translation Failed", "Invalid translation.", 2, $trigger->id);
                    continue;
                }
                
                if($device_access->type == 'mikrotik'){
                    MikrotikHelper::remove(
                        $deviceAccount, 
                        $translate,
                        $requestedData['target_name'], 
                        $requestedData['target_id']
                    );
                    continue;
                } 
                static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Update not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);

            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to inactive:".$ex->getMessage()];

        }

        //DELETE ACCOUNT
        return ["status"=>1, "message"=>"Successfully Removed"];
    }

}