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
use RouterOS\Client as MikroTikClient;
use RouterOS\Query as MikroTikQuery;

class MikrotikHelper
{  
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

    public static function convertCliToApiQuery($cliCommand)
    {
        // Split the command into parts
        $parts = explode(' ', trim($cliCommand));

        // Extract the base command (e.g., "/ppp/secret/add")
        $baseCommand = array_shift($parts);

        // Create the API Query object
        $query = new MikroTikQuery($baseCommand);

        // Parse parameters (e.g., name="user1" password="1234")
        $is_where = false;
        foreach ($parts as $part) {

            if(strtolower($part) == 'where'){
                $is_where = true;
            }


            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $value = trim($value, '"'); // Remove quotes if present
                if($is_where)
                    $query->where($key, $value);
                else
                    $query->equal($key, $value);
            }
        }

        //return $query;
        return[
            "query"=>$query,
            "base_command"=>$baseCommand
        ];
    }

    //return 0 if not exists and -1 if error, -2 empty
    public static function checkAccount($client, $command){
        if($command && !trim($command)){
            return ["status"=>0, "message"=>"Empty Command"]; //EMPTY
        }
        //SAMPLE COMMAND: /ppp/active/print where name="specific-username"
        try{ 
 
            $query = static::convertCliToApiQuery($command); 
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
            $query = static::convertCliToApiQuery($command);
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

    public static function register( Request $request = null, DeviceTemplateTrigger $deviceTrigger, $command, $target_name, $target_id){

        //VALIDATIONS
        $deviceTrigger = DeviceTemplateTrigger::with('device_access')->find($deviceTrigger->id);
        $deviceAccess = $deviceTrigger->device_access;
        if(!$deviceAccess){
            return ["status"=>0, "message"=>"No Device found on trigger."];
        }

        if($deviceAccess->enable_register){
            return ["status"=>0, "message"=>"Register Command not enabled."];
        }

        //SPLIT COMMAND INTO 2
        $lines = array_filter( explode("\n", $command ) );


        $checkRegCommand = $lines[0] ?? "";
        $regCommand = $lines[1] ?? "";


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

        //CHECK EXISTS?

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
                    "is_active"=>false,
                    "active_info"=>"Linked account"
                ]);
            }else{
                DeviceAccount::create([
                    "group_id"=>$deviceTrigger->group_id,
                    "device_template_trigger_id"=>$deviceTrigger->id,
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "account_id"=>$checkRegResult["id"],
                    "is_active"=>false,
                    "active_info"=>"Linked account"
                ]);
            }

            return ["status"=>1, "message"=>"Existed account linked."];
        } 

        //REGISTER
        $registerResult = static::registerAccount($client, $regCommand);
        if($registerResult["status"] == 1){
            
            //CHECK AGAIN USING USERNAME IF EXISTS
            $checkRegResult = static::checkAccount($client, $checkRegCommand);
            if($checkRegResult["status"] == 1 && $checkRegResult["id"] != 0){

                if($request){
                    PayModelHelper::create( DeviceAccount::class, $request, [
                        "device_template_trigger_id"=>$deviceTrigger->id,
                        "target_name"=>$target_name,
                        "target_id"=>$target_id,
                        "account_id"=>$checkRegResult["id"],
                        "is_active"=>false,
                        "active_info"=>"Regisration Successfull"
                    ]);
                }else{
                    DeviceAccount::create([
                        "group_id"=>$deviceTrigger->group_id,
                        "target_name"=>$target_name,
                        "target_id"=>$target_id,
                        "account_id"=>$checkRegResult["id"],
                        "is_active"=>false,
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
 
            $command_lines = $result['command_lines'];

            foreach($command_lines as $command){
                $query = static::convertCliToApiQuery($command);
                $response =  $client->query($query['query'])->read();
                //Error popup
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                }
            }

            if($request){
                PayModelHelper::update($deviceAccount, $request, [ ])->save();
            } else{
                $deviceAccount->save();
            }


            return ["status"=>1, "message"=>"Update Completed."];

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
 
            $command_lines = $result['command_lines'];
            $activeUsers = [];

            foreach($command_lines as $command){

                $query = static::convertCliToApiQuery($command);
                
                $base_command = $query['base_command']; 

                $response =  $client->query($query['query'])->read();

                if($base_command == '/ppp/active/print'){ 
                    $activeUsers = $response;
                    foreach($activeUsers as $activeUser){
                        $query = new MikroTikQuery('/ppp/active/remove');
                        $query->equal('.id', $activeUser['.id']);
                        $response =  $client->query($query)->read();
                        //Error popup
                        if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                            return["status"=>0,"message"=>$response['after']['message']];
                        }
                    }
                }
                
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
 
            $command_lines = $result['command_lines'];

            $activeUsers = [];

            foreach($command_lines as $command){
 
                $query = static::convertCliToApiQuery($command);
                $base_command = $query['base_command']; 

                if($base_command == '/ppp/active/remove' && count($activeUsers)<= 0){
                    continue;
                }
                $response =  $client->query($query['query'])->read();
                
                if($base_command == '/ppp/active/print'){ 
                    $activeUsers = $response;
                }
 
                //Error Popup
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                } 
            }


            //REMOVAL OF SELECTED ACTIVE USERS
            foreach($activeUsers as $activeUser){
                $query = new MikroTikQuery('/ppp/active/remove');
                $query->equal('.id', $activeUser['.id']);
                $response =  $client->query($query)->read();
                //Error popup
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                }
            }

            if($request){
                PayModelHelper::update($deviceAccount, $request, [
                    "is_active"=>false
                ]);
            }else{
                $deviceAccount->is_active = false;
                $deviceAccount->save();
            }


            return ["status"=>1, "message"=>  count($activeUsers)>0 ? "Set Inactive Completed." : "User already been inactive."];

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

        try{

            
            $command_lines = $result['command_lines'];

            $activeUsers = [];

            foreach($command_lines as $command){

                $query = static::convertCliToApiQuery($command);  
                $response =  $client->query($query['query'])->read();
                
                //Error popup
                
                if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
                    return["status"=>0,"message"=>$response['after']['message']];
                }
            }

            if($request){
                PayModelHelper::update($deviceAccount, $request, []);
            }else{
                $deviceAccount->save();
            }


            return ["status"=>1, "message"=>"Set Inactive Completed."];

        }catch(\Exception $ex){
            
            //Log::error($ex->getMessage());
            return ["status"=>0, "message"=>"Error: ".$ex->getMessage()];
        }  
    }

}