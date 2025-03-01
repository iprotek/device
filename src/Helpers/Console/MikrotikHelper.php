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

    //return 0 if not exists and -1 if error, -2 empty
    public static function checkAccount($client, $command){
        if($command && !trim($command)){
            return ["status"=>0, "message"=>"Empty Command"]; //EMPTY
        }
        //SAMPLE COMMAND: /ppp/active/print where name="specific-username"
        try{
            //$query = new MikroTikQuery( $command );
            
            //$query = new Query('/ppp/secret/print');
            //$query->where('name', 'markfuko2'); // Replace with actual username
            //Log::error($query);

            $response = $client->query($command)->read();
            
            Log::error($response);

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

            $query = new MikroTikQuery( $command );
            $client->query($query)->read();
            return ["status"=>1, "message"=>"User added Successfully"];

        }catch(\Exception $ex){
            return ["status"=>0, "message"=>$ex->getMessage()];
        }
    }


    public static function register( Request $request, DeviceTemplateTrigger $deviceTrigger, $command, $target_name, $target_id){

        //VALIDATIONS
        $deviceTrigger = DeviceTemplateTrigger::with('device_access')->find($deviceTrigger->id);
        $deviceAccess = $deviceTrigger->device_access;
        if(!$deviceAccess){
            return ["status"=>0, "message"=>"No Device found on trigger."];
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
            PayModelHelper::create( DeviceAccount::class, $request, [
                "device_template_trigger_id"=>$deviceTrigger->id,
                "target_name"=>$target_name,
                "target_id"=>$target_id,
                "account_id"=>$checkRegResult["id"],
                "is_active"=>true,
                "active_info"=>"Linked account"
            ]);

            return ["status"=>1, "message"=>"Existed account linked."];
        } 

        //REGISTER
        $registerResult = static::registerAccount($client, $regCommand);
        if($registerResult["status"] == 1){
            
            //CHECK AGAIN USING USERNAME IF EXISTS
            $checkRegResult = static::checkAccount($client, $checkRegCommand);
            if($checkRegResult["status"] == 1 && $checkRegResult["id"] != 0){

                PayModelHelper::create( DeviceAccount::class, $request, [
                    "device_template_trigger_id"=>$deviceTrigger->id,
                    "target_name"=>$target_name,
                    "target_id"=>$target_id,
                    "account_id"=>$checkRegResult["id"],
                    "is_active"=>true,
                    "active_info"=>"Registeration Successfull"
                ]);



                return ["status"=>0, "message"=>"Registered Successfully"];
            } 


        }

        return ["status"=>0, "message"=>"failed to register"];
    }

    public static function update($command, DeviceAccount $deviceAccount){
        
    }

    public static function active($command, DeviceAccount $deviceAccount){

    }

    public static function inactive($command, DeviceAccount $deviceAccount){
        
    }

    public static function remove($command, DeviceAccount $deviceAccount){
        
    }

}