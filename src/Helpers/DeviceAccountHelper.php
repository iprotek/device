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

    static $target_id;
    static $target_name;
    static $trigger;
    static $request = null;
    static $command;
    /**
     * Log device access trigger events.
     */
    static function log( $target_name, $target_id, $device_access_id, $command, $response, $log_info, $status_id, $trigger_id, Request $request = null){
        if($request)
        {
            return PayModelHelper::create(DeviceAccessTriggerLog::class, $request, [
                "target_name"=>$target_name,
                "target_id"=> $target_id,
                "device_access_id"=> $device_access_id,
                "command"=> $command,
                "response"=> $response,
                "log_info"=> $log_info,
                "status_id"=> $status_id,
                "device_template_trigger_id"=> $trigger_id,
                "is_resolved"=> $status_id == 2 ? false : true
            ]);
        }
        else{
            return DeviceAccessTriggerLog::create([
                "target_name"=>$target_name,
                "target_id"=> $target_id,
                "device_access_id"=> $device_access_id,
                "command"=> $command,
                "response"=> $response,
                "log_info"=> $log_info,
                "status_id"=> $status_id,
                "device_template_trigger_id"=> $trigger_id,
                "is_resolved"=> $status_id == 2 ? false : true
            ]);
        }
    }

    public static function fail_trigger_log(
        array $response,
        $log_info
    ){
        $trigger = static::$trigger;
        $target_name = static::$target_name;
        $target_id = static::$target_id;
        $request = static::$request;
        $command = static::$command;

        return static::log(
            $target_name,
            $target_id,
            $trigger->device_access_id,
            $command,
            json_encode( $response ),
            $log_info,
            2,
            $trigger->id,
            $request
        );

    }
    public static function success_trigger_log(
        array $response,
        $log_info
    ){
        $trigger = static::$trigger;
        $target_name = static::$target_name;
        $target_id = static::$target_id;
        $request = static::$request;
        $command = static::$command;

        return static::log(
            $target_name,
            $target_id,
            $trigger->device_access_id,
            $command,
            json_encode( $response ),
            $log_info,
            1,
            $trigger->id,
            $request
        );

    }


    //This should be placed when adding new entry
    public static function autoRegister(Request $request, $target_id, $target_name, $branch_id, $field_branch_id='branch_id'){

        //PREVENT ENTRIES FROM EMPTY REQUEST
        if($request === null)
            return;

        //TODO::Test update
        $templateIds = DeviceHelper::allowed_template_trigger_ids($branch_id, $target_name, $target_id, $field_branch_id);

        //checking for active device with enabled register
        $triggers = PayModelHelper::get(DeviceTemplateTrigger::class, $request, ["target_id"=>0, "target_name"=>$target_name, "enable_register"=>1])
                        ->whereIn('id', $templateIds);

        //Check if has trigger in specific branch

        $triggers->with(['device_access']);

        $triggers->whereHas('device_access', function($q)use($branch_id){
            $q->where('is_trigger_registration', 1);
            $q->whereRaw(" JSON_CONTAINS(branch_ids, ? ) ", [json_encode($branch_id)]);
            $q->where('is_active', 1);
        });

        //Execute triggers by Loop
        $triggerList = $triggers->get();

        foreach($triggerList as $trigger){
            static::register($request, $target_name, $target_id, $trigger->id);
        }


    }

    //REGISTER
    public static function register( Request $request, $target_name, $target_id, $device_template_trigger_id){
        static::$request = $request;
        static::$target_name = $target_name;
        static::$target_id = $target_id;
        static::$command = "register";
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

        static::$trigger = $trigger;
        if(!$trigger || $trigger->is_active !== true ){

            static::log( $target_name, $target_id, 0, "--", "Device Trigger not available.", "Device Trigger not available.", 2, $device_template_trigger_id);
            return ["status"=>0,"message"=>"Device Trigger not available."];

        }

        //GET DEVICE
        $device_access = $trigger->device_access;
        if(!$device_access || $device_access->is_active !== true){

            static::fail_trigger_log(
                [],  
                "Device Access is not available."
            );
            return ["status"=>0,"message"=>"Device Access is not available."];

        }

        //CHECK IF ALLOW REGISTER
        if($trigger->enable_register !== true){
            static::fail_trigger_log(
                [],  
                "Register is disabled."
            );
            return ["status"=>0, "message"=>"Register is disabled."];
        
        }
        
        $template = $trigger->register_command_template;
        //CONVERT TEMPLATE TRANSLATION
        $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
        if(is_array($translate) && $translate["status"] == 0){
                 
            static::fail_trigger_log(
                $translate,  
                "Translate error: ".$translate["message"]
            );

            return $translate;

        }
        if( !is_string( $translate)){
            static::fail_trigger_log(
                $translate,  
                "Translate error: ".$translate["message"]
            );
                
            return ["status"=>0, "message"=>"Invalid Command "];
        }
        //return ["status"=>0, "message"=>$translate];

        //IF MIKROTIK
        if($device_access->type == 'mikrotik'){
            $result = \iProtek\Device\Helpers\Console\MikrotikHelper::register(
                $request,
                $trigger,
                $translate,
                $requestedData['target_name'],
                $requestedData['target_id']
            );
            if($result["status"] != 1){
                static::fail_trigger_log(
                    $result,  
                    "Registration failed to ".static::$target_name
                );
            }
            else{
                
                static::success_trigger_log(
                    $result,  
                    "Registration for ".$requestedData['target_name']
                );
            }
            return $result;     
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
    public static function update($target_name, $target_id, $force=false, $field_branch_id='branch_id'){
        
        static::$target_name = $target_name;
        static::$target_id = $target_id;
        static::$command = "update";

        $current_device_trigger = null;
        try{

            $templateIds = DeviceHelper::allowed_template_trigger_ids(0, $target_name, $target_id, $field_branch_id);

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::whereIn('id', $templateIds)->with(['device_access'])->where(['target_name'=> $target_name, 'enable_update'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log($target_name, $target_id, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                static::$trigger = $trigger;
                
                
                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id
                ];
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();

                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    static::fail_trigger_log(
                        [],
                         "Account not exist for activating."
                    );
                    
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::fail_trigger_log(
                        [],
                        "Device is inactive or not found!"
                    );
                    continue;
                }
                
                //GET TEMPLATE TRIGGER INFO
                
                $template = $trigger->update_command_template;
                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::fail_trigger_log(
                        $template,
                        "Translation Failed"
                    );
                    continue;
                }
                if( !is_string( $translate)){                    
                    static::fail_trigger_log(
                        $template,
                        "Translation Failed(2)"
                    );
                    continue;
                }

                
                if($device_access->type == 'mikrotik'){
                   $result =  MikrotikHelper::update(
                        $deviceAccount, 
                        $translate,
                        $target_name,
                        $target_id
                    );
                    if($result["status"] != 1){
                        static::fail_trigger_log(
                            $result,
                            "Update Failed: ".$result["message"]
                        );
                        return $result;
                    }
                    else{                  
                        static::success_trigger_log(
                            $result,
                            "Update for $target_name"
                        );

                    }
                    continue;
                }
                else{
                    static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Update not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);
                }
            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to activate:".$ex->getMessage()];

        }


        return ["status"=>1, "message"=>"Successfully Updated."];
    }   

    //ACTIVATE
    public static function active($target_name, $target_id, $force=false, $field_branch_id='branch_id'){

        static::$target_name = $target_name;
        static::$target_id = $target_id;
        static::$command = "active";

        $current_device_trigger = null;
        try{
            
            $templateIds = DeviceHelper::allowed_template_trigger_ids(0, $target_name, $target_id, $field_branch_id);

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::whereIn('id', $templateIds)->with(['device_access'])->where(['target_name'=> $target_name, 'enable_active'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log( $target_name, 0, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                static::$trigger = $trigger;
                
                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id
                ];
                
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();

                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    static::fail_trigger_log(
                        [],
                        "Account not exist for activating."
                    );
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                

                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::fail_trigger_log(
                        [],
                        "Device is inactive or not found."
                    );
                    continue;
                }

                $template = $trigger->active_command_template;

                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::fail_trigger_log(
                        $template,
                        "Translation Failed"
                    );
                    continue;
                }
                if( !is_string( $translate)){
                    static::fail_trigger_log(
                        $template,
                        "Translation Failed(2)"
                    );
                    continue;
                }

                
                if($device_access->type == 'mikrotik'){
                    $result = MikrotikHelper::active(
                        $deviceAccount, 
                        $translate,
                        $requestedData['target_name'],
                        $requestedData['target_id']
                    );
                    if($result["status"] != 1){
                        static::fail_trigger_log(
                            $result,
                            "Active Failed: ".$result["message"]
                        );
                        return $result;
                    }
                    else{                  
                        static::success_trigger_log(
                            $result,
                            "Active for $target_name"
                        );
                    }
                    continue;
                }
                else{
                    static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Registration not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);
                }


            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to activate:".$ex->getMessage()];

        }


        return ["status"=>1, "message"=>"Successfully Activated."];
        
    }

    //INACTIVE
    public static function inactive($target_name, $target_id, $force=false, $field_branch_id='branch_id'){
        
        static::$target_name = $target_name;
        static::$target_id = $target_id;
        static::$command = "inactive";

        $current_device_trigger = null;
        try{

            $templateIds = DeviceHelper::allowed_template_trigger_ids(0, $target_name, $target_id, $field_branch_id);

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::whereIn('id', $templateIds)->with(['device_access'])->where(['target_name'=> $target_name, 'enable_inactive'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log($target_name, $target_id, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                static::$trigger = $trigger;

                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id
                ];
                
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();


                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    //static::log($target_name, $target_id, $trigger->device_access_id, "--", "Account not exist for deactivation.", "Account not exist for activating.", 2, $trigger->id);
                    static::fail_trigger_log(
                        [],
                        "Account not exist for activating."
                    );
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::fail_trigger_log(
                        [],
                        "Device is inactive or not found."
                    );
                    continue;
                }

                //GET TEMPLATE TRIGGER INFO
                $template = $trigger->inactive_command_template;
                
                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::fail_trigger_log(
                        $translate,
                        "failed to translate."
                    );
                    continue;
                }
                if( !is_string( $translate)){
                    static::fail_trigger_log(
                        $translate,
                        "Invalid translation(2)."
                    );
                    continue;
                }

                
                if($device_access->type == 'mikrotik'){
                    $result = MikrotikHelper::inactive(
                        $deviceAccount, 
                        $translate,
                        $requestedData['target_name'],
                        $requestedData['target_id']
                    );
                    if($result["status"] != 1){
                        static::fail_trigger_log(
                            $result,
                            "Inactive Failed: ".$result["message"]
                        );
                        return $result;
                    }
                    else{                  
                        static::success_trigger_log(
                            $result,
                            "Inactive for $target_name"
                        );
                    }
                    continue;
                }
                else{
                    static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Update not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);
                }
            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to inactive:".$ex->getMessage()];

        }



        return ["status"=>1, "message"=>"Successfully Inactive."];

    }

    //REMOVE
    public static function remove($target_name, $target_id, $force=false, $field_branch_id='branch_id'){

        static::$target_name = $target_name;
        static::$target_id = $target_id;
        static::$command = "remove";

        $current_device_trigger = null;
        try{

            $templateIds = DeviceHelper::allowed_template_trigger_ids(0, $target_name, $target_id, $field_branch_id);

            //CHECK ACTIVE TRIGGERS THEN ACTIVATE THE ACCOUNT ASSOCIATED
            $device_triggers = DeviceTemplateTrigger::where('id', $templateIds)->with(['device_access'])->where(['target_name'=> $target_name, 'enable_remove'=>true, 'is_active'=>true])->get();


            if( count($device_triggers) <= 0){
                static::log($target_name, $target_id, 0, "--", "Unable to active empty trigger specified.", "Failed to register empty trigger specified.", 2, 0);
                return ["status"=>0, "message"=>"No trigger found."];
            }

            foreach($device_triggers as $trigger){
                
                $current_device_trigger = $trigger;
                static::$trigger = $trigger;

                $requestedData = [
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "device_template_trigger_id"=>$current_device_trigger->id,
                ];
                
                
                //CHECK IF ACCOUNT EXISTS
                $deviceAccount = DeviceAccount::where('group_id', $current_device_trigger->group_id)->where($requestedData)->first();

                if(!$deviceAccount){
                    //RECORD EXISTENCE
                    static::fail_trigger_log(
                        [],
                        "Account not exist for activating."
                    );
                    continue;
                }
                //PREVENT FROM TRIGGERS
                else if(!$force && !$deviceAccount->is_auto_trigger)
                    continue;
                
                $device_access = $trigger->device_access;
                if(!$device_access || $device_access->is_active !== true){
                    static::fail_trigger_log(
                        [],
                        "Device is inactive or not found."
                    );
                    continue;
                }
                
                //GET TEMPLATE TRIGGER INFO
                $template = $trigger->remove_command_template;
                
                //CONVERT TEMPLATE TRANSLATION
                $translate = DeviceHelper::translate_template($template, $requestedData['target_name'], $requestedData['target_id']);
                if(is_array($translate) && $translate["status"] == 0){
                    static::fail_trigger_log(
                        $template,
                        "failed to translate."
                    );
                    continue;
                }
                if( !is_string( $translate)){
                    static::fail_trigger_log(
                        $template,
                        "Invalid translation(2)."
                    );
                    continue;
                }
                
                if($device_access->type == 'mikrotik'){
                    $result = MikrotikHelper::remove(
                        $deviceAccount, 
                        $translate,
                        $requestedData['target_name'], 
                        $requestedData['target_id']
                    );
                    if($result["status"] != 1){
                        static::fail_trigger_log(
                            $result,
                            "Remove Failed: ".$result["message"]
                        );
                        return $result;
                    }
                    else{                  
                        static::success_trigger_log(
                            $result,
                            "Remove for $target_name"
                        );
                    }
                    continue;
                } 
                else{
                    static::log( $target_name, $target_id, $trigger->device_access_id, $translate, "Update not yet available for:".$device_access->type, "Registration not yet available for:".$device_access->type, 2, $trigger->id);
                }
            }
            
        }catch(\Exception $ex){
            return ["status"=>0, "message"=>"Failed to inactive:".$ex->getMessage()];
        }

        //DELETE ACCOUNT
        return ["status"=>1, "message"=>"Successfully Removed"];
    }

}