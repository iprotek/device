<?php
namespace iProtek\Device\Helpers\Console;

use DB; 
use Illuminate\Http\Request; 
use iProtek\Core\Helpers\PayModelHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use iProtek\Device\Models\DeviceAccount;
use iProtek\Device\Models\DeviceAccess;
use iProtek\Device\Models\DeviceTemplateTrigger;
use MikrotikAPI\MikrotikAPI;
//use RouterOS\Client as MikroTikClient;
//use RouterOS\Query as MikroTikQuery;
use iProtek\Device\Helpers\MClientHelper as MikroTikClient;
use iProtek\Device\Helpers\MQueryHelper as MikroTikQuery;
use iProtek\Device\Helpers\DeviceHelper;
use iProtek\Device\Helpers\DeviceVariableHelper;

class MikrotikHelper
{  

    public static function execute(DeviceAccess $deviceAccess, MikroTikQuery $query){
        $result = null;

        //Check if device is mikrotik
        if($deviceAccess->type != 'mikrotik'){
            return ["status"=>0, "message"=>"Only Mikrotik accepted."];
        }

        $login = static::credential_login_check( [
            "host"=>$deviceAccess->host,
            "user"=>$deviceAccess->user,
            "password"=>$deviceAccess->password,
            "port"=>$deviceAccess->port,
            'is_ssl'=>$deviceAccess->is_ssl
        ] , true);

        if($login['status'] == 0)
                return $login;

        try{

            $client = $login['client'];
            $result = $client->query($query)->read();
        
        }catch (\Exception $e) {
            return [ "status"=>0, "message"=> "Command invalidated." ];
        }


        return ["status"=>1, "message"=>"Successfully Executed.", "result"=>$result];
    }



    public static function credential_login_check(array $credential, $get_client = false){
        //CREDENTIAL CHECK
        //host
        //user
        //port
        //password
        //Log::error($credential);
        
        $host = $credential['host'];
        $user = $credential['user'];
        $pass = $credential['password'];
        $port = (int)$credential['port'];
        $is_ssl = $credential['is_ssl'];
        //Log::error($credential);
        
        //EVILFREELANCER

        $command = '/system/identity/print';

        try {
            // Establish connection to MikroTik
            $client = new MikroTikClient([
                'host' => $host,  // Change to your MikroTik IP
                'user' => $user,          // Change to your MikroTik username
                'pass' => $pass,       // Change to your MikroTik password
                'port' => $port,             // API port (8728 for HTTP, 8729 for HTTPS)
                "ssl" => $is_ssl,
            ]);

        
            // Example: Get router identity
            $query = new MikroTikQuery( $command );
            $response = $client->query($query)->read();
        
            //$response = $client->query(  $command )->read();
            //$response = $client->export();

            //Log::error($response);
            //print_r($response);
            if($get_client){
                return ["status" =>1, "client"=>$client];
            }

            return [ "status"=>1, "message"=>"Login Success" ,"result"=>json_encode($response), "command"=>$command ];
        } catch (\Exception $e) {
            //Log::error( $e->getMessage() );
            return [ "status"=>0, "message"=> $e->getMessage(), "command"=>$command ];
        }

    }

    public static function find_command($client, $baseLine, $keyValues){
        $query = new MikroTikQuery($baseLine);
        //$query->operations('&');
        foreach ($keyValues as $key => $value) {
            $query->where($key, $value);
        }
        
        $response = $client->query($query)->read();

        
        if(is_array($response) && isset($response["after"]) && isset($response["after"]["message"] )){
            //Log::error($response);
            return '.id="E1:*0**"';
        }
    
        if(is_array($response) && count($response) > 0){
            return ".id=\"".$response[0][".id"].'"';
        }
        //Log::error($response);
        return '.id="E2:*0**"';
    }

    public static function convertCliToApiQuery($cliCommand, callable $fn=null)
    {

        //CHECK FIND
        $cliCommand = DeviceVariableHelper::find($cliCommand, $fn);



        // Split the command into parts
        $testVar = explode(' ', trim($cliCommand));
        $baseCommand = array_shift( $testVar );
        //preg_match('/^([^\s]+)/', $cliCommand, $parts);
        preg_match_all('/([^\s=]+)="([^"]*)"/', $cliCommand, $matches, PREG_SET_ORDER);
        $parts = [];
        foreach($matches as $match){
            if($match[0]){
                $parts[] = $match[0];
            }
        }

        // Extract the base command (e.g., "/ppp/secret/add")
        //$baseCommand = array_shift($parts);

        // Create the API Query object
        $query = new MikroTikQuery($baseCommand);

        // Parse parameters (e.g., name="user1" password="1234")
        $is_where = preg_match('#^/\S+/print(\s|$)#', $baseCommand) ? true : false;
        foreach ($parts as $part) {

            /*
            if(strtolower($part) == 'where'){
                $is_where = true;
            }
            else if(strpos($part, '=') === false && strpos($part, '~') === false){
                $is_where = false;
            }
            */

            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                
                $value = trim($value, '"'); // Remove quotes if present
                if($is_where){
                    $query->where($key, $value);
                }
                else{
                    $query->equal($key, $value);
                }
            }
            else if(strpos($part, '~') !== false){
                preg_match('/^([a-zA-Z0-9\-]+)([~!=]+)"(.+)"$/', $part, $matches);
                $field    = $matches[1]; // mac-address
                $operator = $matches[2]; // ~
                $value    = $matches[3];
                $query->where($field, $operator, "/$value/");
            }
        }

        //return $query;
        return[
            "query"=>$query,
            "base_command"=>$baseCommand,
            "full_command"=>$cliCommand
        ];
    }

    //return 0 if not exists and -1 if error, -2 empty
    public static function checkAccount($client, $command){
        if($command && !trim($command)){
            return ["status"=>0, "message"=>"Empty Command"]; //EMPTY
        }
        //SAMPLE COMMAND: /ppp/active/print where name="specific-username"
        try{ 
 
            $query = static::convertCliToApiQuery($command, function($baseLine, $keyValues)use($client){
                return static::find_command($client, $baseLine, $keyValues);
            }); 
            $response = $client->query($query['query'])->read();
            
            if(is_array($response) && isset($response["after"]) && isset($response["after"]["message"] )){
                return ["status"=>0, "message"=> $response["after"]["message"]];
            }

            if(is_array($response) && count($response) > 0){
                return [ "status"=>1, "message"=>"Account Found!", "id"=> $response[0][".id"], "account_info"=>$response[0] ];
            }

        }catch(\Exception $ex){
            return ["status"=>0, "message"=>$ex->getMessage()];
        }
        return ["status"=>1, "message"=>"Account not exists", "id"=>0];
    }

    //
    public static function registerAccount($client, $command){
        if($command && !trim($command)){
            return ["status"=>0, "message"=>"Empty Command"]; //EMPTY
        }
        //SAMPLE COMMAND: /ppp/secret/add name="newuser" password="securepass" service="pppoe" profile="default"
        try{

            //$query = new MikroTikQuery( $command );
            //Log::error($command);
            $query = static::convertCliToApiQuery($command, function($baseLine, $keyValues)use($client){
                return static::find_command($client, $baseLine, $keyValues);
            });
            $response =  $client->query($query['query'])->read();
            //Log::error($response);

            if(is_array($response) && isset($response["after"]) && isset($response["after"]["message"] )){
                return ["status"=>0, "message"=> $response["after"]["message"]];
            }


            return ["status"=>1, "message"=>"User added Successfully"];

        }catch(\Exception $ex){
            
           // Log::error($ex->getMessage());
            return ["status"=>0, "message"=>$ex->getMessage()];
        }
    }

    public static function commandResultValidation($commandStr, DeviceAccount $deviceAccount){
        
        //VALIDATIONS
        $deviceTrigger = DeviceTemplateTrigger::with('device_access')->find($deviceAccount->device_template_trigger_id);
        $deviceAccess = $deviceTrigger->device_access;
        if(!$deviceAccess){
            return ["status"=>0, "message"=>"No Device found on trigger."];
        }

        //SPLIT COMMAND INTO 2
        $lines = array_filter( explode("\n", $commandStr ) );
        if(count($lines)<= 0){
            return ["status"=>0, "message"=>"Empty Command"];
        }


        if($deviceAccess->type != 'mikrotik'){
            return [ "status"=>0, "message"=>"Invalid Device"];
        }

        //CHECK ACCOUNT USING USERNAME
        $credential = [
            "host"=>$deviceAccess->host,
            "user"=>$deviceAccess->user,
            "password"=>$deviceAccess->password,
            "port"=>$deviceAccess->port,
            "is_ssl"=>$deviceAccess->is_ssl
        ];

        //LOGIN VALIDATION
        $checkLogin = static::credential_login_check($credential, true);
        if($checkLogin["status"] !== 1 ){
            return $checkLogin;
        }

        $client = $checkLogin["client"];

        return [ 
            "status"=>1, 
            "device_trigger"=>$deviceTrigger, 
            "command_lines"=>$lines, 
            "device_access"=>$deviceAccess,
            "client"=>$client
        ];
    }

    public static function register( Request $request = null, DeviceTemplateTrigger $deviceTrigger, $translate, $target_name, $target_id){

        //VALIDATIONS
        $deviceTrigger = DeviceTemplateTrigger::with('device_access')->find($deviceTrigger->id);
        $deviceAccess = $deviceTrigger->device_access;
        if(!$deviceAccess) return ["status"=>0, "message"=>"No Device found on trigger."];
        if($deviceAccess->enable_register)  return ["status"=>0, "message"=>"Register Command not enabled."];
        if($deviceAccess->type != 'mikrotik') return [ "status"=>0, "message"=>"Invalid Device"];

        //CHECK ACCOUNT USING USERNAME
        $credential = [
            "host"=>$deviceAccess->host,
            "user"=>$deviceAccess->user,
            "password"=>$deviceAccess->password,
            "port"=>$deviceAccess->port,
            "is_ssl"=>$deviceAccess->is_ssl
        ];
        //LOGIN VALIDATION
        $checkLogin = static::credential_login_check($credential, true);
        if($checkLogin["status"] !== 1 ){
            return $checkLogin;
        }
        $client = $checkLogin["client"];


        $result = MikrotikScriptHelper::executeNow($translate, $client, false);

        //Log::error($translate);
        //Log::error($result);
        //return ["status"=>0, "message"=>$translate]
        if(!$result || !is_array($result)){
            return ["status"=>0, "message"=>"Something goes wrong"];
        }
        if($result["status"] != 1){
            return $result;
        }
        
                
        //UPDATE THE TARGET FIELDS
        $context = $result["context"];
        if(!$context){
            return ["status"=>0, "message"=>"Please contact administrator for the error."];
        }

        $_updates = $result["context"]["_updates"] ?? "";
        if($_updates){
            $updateResult = static::targetUpdates($_updates , $target_name, $target_id);
            if($updateResult["status"] != 1) 
                return $updateResult;
        }

        $addedId = "";
        $message = "";
        if(isset($context["_status"])){
            if($context["_status"] == "0")
                return["status"=>0, "message"=>$context["_message"] ?? "Unidentified error inside script."];
            if(isset($context["_id"]) && $context["_id"] )
                $addedId = $context["_id"];
        }

        if(!$addedId && count($context["_added_ids"])){
            $addedId = $context["_added_ids"][0];
        }
        if(!trim($addedId)){
            return ["status"=>0, "message"=>"failed to add"];
        }

        $message = $context["_message"] ?? "Successfully Registered.";
        $requestData = [
            "device_template_trigger_id"=>$deviceTrigger->id,
            "target_name"=>$target_name,
            "target_id"=>$target_id,
            "account_id"=>$addedId,
            "is_active"=>1,
            "active_info"=>"Regisration Successfull"
        ];

        if($request){
            PayModelHelper::create( DeviceAccount::class, $request, $requestData);
        }else{
            DeviceAccount::create($requestData);
        }
        //GETTING THE DESIRED ID RESULT        
        //LOG HERE FOR ERROR
        return ["status"=>1, "message"=>$message];




        //SPLIT COMMAND INTO 2
        $lines = array_filter( explode("\n", $translate ) );
        $checkRegCommand = $lines[0] ?? "";
        $regCommand = $lines[1] ?? "";


        //CHECK EXISTS?

        //CHECK IF FIRST COMMAND IS ADD
        $getIds = [];



        //REGISTER IF NOT EXISTS
        $checkRegResult = static::checkAccount($client, $checkRegCommand);
        if($checkRegResult["status"] != 1){
            return $checkRegResult;
        }
        else if($checkRegResult["id"] !== 0){

            //CHECK IF ACCOUNT IS REGISTERED USING THE TRIGGER ID
            $exists = DeviceAccount::where([
                "group_id"=>$deviceTrigger->group_id,
                "device_template_trigger_id"=>$deviceTrigger->id,
                "target_name"=>$target_name,
                //"target_id"=>$target_id,
                "account_id"=>$checkRegResult["id"]
            ])->first();

            if($exists){
                return ["status"=>1, "message"=>"Account Already been linked."];
            }

            //SAVING LINKED ACCOUNT
            if($request){
                PayModelHelper::create( DeviceAccount::class, $request, [
                    "device_template_trigger_id"=>$deviceTrigger->id,
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "account_id"=>$checkRegResult["id"],
                    "is_active"=>1,
                    "active_info"=>"Linked account"
                ]);
            }else{
                DeviceAccount::create([
                    "group_id"=>$deviceTrigger->group_id,
                    "device_template_trigger_id"=>$deviceTrigger->id,
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "account_id"=>$checkRegResult["id"],
                    "is_active"=>1,
                    "active_info"=>"Linked account"
                ]);
            }

            return ["status"=>1, "message"=>"Existed account linked."];
        } 

        //REGISTER
        $registerResult = static::registerAccount($client, $regCommand);
        if($registerResult["status"] == 1){
            sleep(2);
            //CHECK AGAIN USING USERNAME IF EXISTS
            $checkRegResult = static::checkAccount($client, $checkRegCommand);
            if($checkRegResult["status"] == 1 && $checkRegResult["id"] != 0){

                if($request){
                    PayModelHelper::create( DeviceAccount::class, $request, [
                        "device_template_trigger_id"=>$deviceTrigger->id,
                        "target_name"=>$target_name,
                        "target_id"=>$target_id,
                        "account_id"=>$checkRegResult["id"],
                        "is_active"=>1,
                        "active_info"=>"Regisration Successfull"
                    ]);
                }else{
                    DeviceAccount::create([
                        "group_id"=>$deviceTrigger->group_id,
                        "target_name"=>$target_name,
                        "target_id"=>$target_id,
                        "account_id"=>$checkRegResult["id"],
                        "is_active"=>1,
                        "active_info"=>"Regisration Successfull"
                    ]);
                }
                return ["status"=>1, "message"=>"Registered Successfully"];
            } 


        }else{
            return $registerResult;
        }

        return ["status"=>0, "message"=>"failed to register"];
    }

    static function targetUpdates($updateStr, $target_name, $target_id){

        $result = [];

        foreach (explode(',', $updateStr) as $item) {

            $item = trim($item);

            // skip empty values
            if (empty($item)) {
                continue;
            }

            // split only on first =
            [$key, $value] = explode('=', $item, 2);

            // normalize key
            $key = str_replace('-', '_', trim($key));
            if(!$key) continue;
            // clean value
            $value = trim($value);
            if( in_array( strtolower($key),["id", "branch_id", "group_id", "updated_at", "created_at", "deleted_at", "pay_created_at", "pay_updated_at"]) ) continue;
            $result[$key] = $value;
        }
        if(count($result) > 0){
            $model = DeviceVariableHelper::getModelByTable($target_name);
            if(!$model){
                return ["status"=>0, "message"=>"Unable to update ".$target_name];
            }
            $target = (new $model)->find($target_id);
            if($target){
                $target->timestamps = false;
                foreach(array_keys($result) as $key){
                    if(!DeviceVariableHelper::modelHasColumn($model, $key)){
                        return ["status"=>0, "message"=>"Not exists column $key at $target_name"];
                    }
                    $target->{$key} = $result[$key];
                }
                $target->save();
                return ["status"=>1, "message"=>"Successfully updated fields"];
            }
            return ["status"=>0, "message"=>"Specified on $target_name cannot be found."];
        }
        return ["status"=>1, "message"=>"Passed.. no updates detected."];
    }

    public static function update(  DeviceAccount $deviceAccount, $commandStr, $target_name, $target_id, Request $request = null){
        
        $trigger =  DeviceTemplateTrigger::find($deviceAccount->device_template_trigger_id);
        if( !$trigger->enable_update ){
            return ["status"=>0, "message"=>"Update Command not enabled."];
        }



        $result = static::commandResultValidation($commandStr, $deviceAccount);

        if($result['status'] != 1){
            return $result;
        }

        $client = $result['client'];

        try{
            
            
            $result = MikrotikScriptHelper::executeNow($commandStr, $client, false);

            if($result["status"] != 1){
                return $result;
            }

            $status = $result["status"];
            $message = "Account has now updated.";
            
            $context = $result["context"];
            if(!$context){
                return ["status"=>0, "message"=>"Please contact administrator for the error."];
            }

            if( isset( $context["_status"] ) )
                $status = $context["_status"] == "1" ? 1:0;

            if( isset( $context["_message"] ) )
                $message = $context["_message"];

            if($status == "1"){
                if($request){
                    PayModelHelper::update($deviceAccount, $request, [
                        "is_active"=>false
                    ]);
                }else{
                    $deviceAccount->is_active = false;
                    $deviceAccount->save();
                }
            }

            return ["status"=>$status, "message"=>$message];

            /*
 
            $command_lines = $result['command_lines'];

            foreach($command_lines as $command){
                $query = static::convertCliToApiQuery($command,function($baseLine, $keyValues)use($client){
                    return static::find_command($client, $baseLine, $keyValues);
                });
                $response =  $client->query($query['query'])->read();
                //Error popup
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                }
            }

            if($request){
                PayModelHelper::update($deviceAccount, $request, [ ]);
            } else{
                $deviceAccount->save();
            }


            return ["status"=>1, "message"=>"Update Completed."];
            */

        }catch(\Exception $ex){
            
            //Log::error($ex->getMessage());
            return ["status"=>0, "message"=>$ex->getMessage()];
        }

    }

    public static function active( DeviceAccount $deviceAccount, $commandStr, $target_name, $target_id, Request $request = null){

        
        $trigger =  DeviceTemplateTrigger::find($deviceAccount->device_template_trigger_id);
        if( !$trigger->enable_active ){
            return ["status"=>0, "message"=>"Set Active command not enabled."];
        }


        $result = static::commandResultValidation($commandStr, $deviceAccount);

        if($result['status'] != 1){
            return $result;
        }

        $client = $result['client'];

        try{
            
            $result = MikrotikScriptHelper::executeNow($commandStr, $client, false);

            if($result["status"] != 1){
                return $result;
            }

            $status = $result["status"];
            $message = "Account Reactivated.";
            
            $context = $result["context"];
            if(!$context){
                return ["status"=>0, "message"=>"Please contact administrator for the error."];
            }

            if( isset( $context["_status"] ) )
                $status = $context["_status"] == "1" ? 1:0;

            if( isset( $context["_message"] ) )
                $message = $context["_message"];

            if($status == "1"){
                if($request){
                    PayModelHelper::update($deviceAccount, $request, [
                        "is_active"=>false
                    ]);
                }else{
                    $deviceAccount->is_active = false;
                    $deviceAccount->save();
                }
            }

            return ["status"=>$status, "message"=>$message];
            /*
            $command_lines = $result['command_lines'];
            $activeUsers = [];

            foreach($command_lines as $command){

                $query = static::convertCliToApiQuery($command, function($baseLine, $keyValues)use($client){
                    return static::find_command($client, $baseLine, $keyValues);
                });
                
                $base_command = $query['base_command']; 

                $response =  $client->query($query['query'])->read();

                
                //if($base_command == '/ppp/active/print'){ 
                //    $activeUsers = $response;
               //     foreach($activeUsers as $activeUser){
                //        $query = new MikroTikQuery('/ppp/active/remove');
                //        $query->equal('.id', $activeUser['.id']);
                //        $response =  $client->query($query)->read();
                        //Error popup
               //         if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                //            return["status"=>0,"message"=>$response['after']['message']];
                //        }
                //    }
               // }
                
                
                //Error pop up
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                }
            }

            if($request){
                PayModelHelper::update($deviceAccount, $request, [
                    "is_active"=>true
                ]);
            }else{
                $deviceAccount->is_active = true;
                $deviceAccount->save();
            }


            return ["status"=>1, "message"=>"Set Active Completed."];
            */

        }catch(\Exception $ex){
            
            //Log::error($ex->getMessage());
            return ["status"=>0, "message"=>$ex->getMessage()];
        }
    }

    public static function inactive(DeviceAccount $deviceAccount, $commandStr, $target_name, $target_id, Request $request = null){
        
        
        $trigger =  DeviceTemplateTrigger::find($deviceAccount->device_template_trigger_id);
        if( !$trigger->enable_inactive ){
            return ["status"=>0, "message"=>"Set Inactive Command not enabled."];
        }

        $result = static::commandResultValidation($commandStr, $deviceAccount);

        if($result['status'] != 1){
            return $result;
        }

        $client = $result['client'];

        try{
        
            $result = MikrotikScriptHelper::executeNow($commandStr, $client, false);

            if($result["status"] != 1){
                return $result;
            }

            $status = $result["status"];
            $message = "Account Inactivated.";
            
            $context = $result["context"];
            if(!$context){
                return ["status"=>0, "message"=>"Please contact administrator for the error."];
            }

            if( isset( $context["_status"] ) )
                $status = $context["_status"] == "1" ? 1:0;

            if( isset( $context["_message"] ) )
                $message = $context["_message"];

            if($status == "1"){
                if($request){
                    PayModelHelper::update($deviceAccount, $request, [
                        "is_active"=>false
                    ]);
                }else{
                    $deviceAccount->is_active = false;
                    $deviceAccount->save();
                }
            }

            return ["status"=>$status, "message"=>$message];
            /*
            $command_lines = $result['command_lines'];

            $activeUsers = [];

            foreach($command_lines as $command){
 
                $query = static::convertCliToApiQuery($command, function($baseLine, $keyValues)use($client){
                    return static::find_command($client, $baseLine, $keyValues);
                });
                $base_command = $query['base_command']; 

                if($base_command == '/ppp/active/remove' && count($activeUsers)<= 0){
                    continue;
                }
                $response =  $client->query($query['query'])->read();
                
                //if($base_command == '/ppp/active/print'){ 
                //    $activeUsers = $response;
                //}
 
                //Error Popup
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                } 
            }
            */

            //REMOVAL OF SELECTED ACTIVE USERS
            /*
            foreach($activeUsers as $activeUser){
                $query = new MikroTikQuery('/ppp/active/remove');
                $query->equal('.id', $activeUser['.id']);
                $response =  $client->query($query)->read();
                //Error popup
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                }
            }*/
            /*
            if($request){
                PayModelHelper::update($deviceAccount, $request, [
                    "is_active"=>false
                ]);
            }else{
                $deviceAccount->is_active = false;
                $deviceAccount->save();
            }


            return ["status"=>1, "message"=>  "Done Inactive."];
            */

        }catch(\Exception $ex){
            
            //Log::error($ex->getMessage());
            return ["status"=>0, "message"=>$ex->getMessage()];
        }
    }

    public static function remove(DeviceAccount $deviceAccount, $commandStr, $target_name, $target_id, Request $request = null){
      
        $trigger =  DeviceTemplateTrigger::find($deviceAccount->device_template_trigger_id);
        if( !$trigger->enable_remove ){
            return ["status"=>0, "message"=>"Remove command is not enabled."];
        }

        $result = static::commandResultValidation($commandStr, $deviceAccount);

        if($result['status'] != 1){
            return $result;
        }

        $client = $result['client'];
        $errors = [];
        //try{
            
            $result = MikrotikScriptHelper::executeNow($commandStr, $client, false);

            if($result["status"] != 1){
                return $result;
            }

            $status = $result["status"];
            $message = "Successfully Removed";
            
            $context = $result["context"];
            if(!$context){
                return ["status"=>0, "message"=>"Please contact administrator for the error."];
            }

            if( isset( $context["_status"] ) )
                $status = $context["_status"] == "1" ? 1:0;

            if( isset( $context["_message"] ) )
                $message = $context["_message"];

            if($status == "1"){
                if($request)
                    PayModelHelper::delete($deviceAccount, $request);
                else 
                    $deviceAccount->delete();
            }


            return ["status"=>$status, "message"=>$message];

            /*
            
            
            $command_lines = $result['command_lines'];

            $activeUsers = [];

            foreach($command_lines as $command){

                $query = static::convertCliToApiQuery($command, function($baseLine, $keyValues)use($client){
                    return static::find_command($client, $baseLine, $keyValues);
                });  
                $response =  $client->query($query['query'])->read();
                
                //Error popup
                
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    //return["status"=>0,"message"=>$response['after']['message']];
                    $errors[] = $response['after']['message'];
                }
            }
            if($request)
                PayModelHelper::delete($deviceAccount, $request);
            else 
                $deviceAccount->delete();
           
           // if($request){
           //     PayModelHelper::update($deviceAccount, $request, []);
           // }else{
            //    $deviceAccount->save();
            //}
        
            if(count($errors)>0){
                return ["status"=>1, "message"=>"Removed with errors. (".implode(',', $errors).")"];
            }

            return ["status"=>1, "message"=>"Removed successfully."];
            */

        //}catch(\Exception $ex){
            
            //Log::error($ex->getMessage());
            //return ["status"=>0, "message"=>"Error: ".$ex->getMessage()];
        //}  
    }


}