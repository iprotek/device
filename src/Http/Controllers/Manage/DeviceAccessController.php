<?php

namespace iProtek\Device\Http\Controllers\Manage;

use Illuminate\Http\Request; 
use iProtek\Core\Http\Controllers\_Common\_CommonController;
use iProtek\Core\Helpers\PayHttp;
use iProtek\Core\Helpers\PayModelHelper;

use iProtek\Device\Models\DeviceAccess;
use iProtek\Device\Helpers\Console\MikrotikHelper;
use iProtek\Device\Helpers\Console\SshHelper;
use iProtek\Device\Models\DeviceAccessTriggerLog;

class DeviceAccessController extends _CommonController
{
    public $guard = "admin";
    //
    public function list(Request $request){
        $deviceList = PayModelHelper::get(DeviceAccess::class, $request, []);

        if($request->search_text){
            $search_text = '%'.str_replace(' ', '%', $request->search_text).'%';
            $deviceList->whereRaw(' CONCAT(type,name,user,port) LIKE ?', [$search_text]);
        }
        if($request->has('is_active')){
            $deviceList->where('is_active', $request->is_active);
        }

        if($request->has('type')){
            $deviceList->where('type', $request->type);
        }


        return $deviceList->paginate(10);
    }

    public function dynamic_selection(Request $request){

        $data_schema = $request->data_schema;

        $dynamic_table = \DB::table($data_schema);

        if(\Illuminate\Support\Facades\Schema::hasColumn($data_schema, 'group_id')){
            $dynamic_table->where('group_id', PayHttp::target_group_id($request) );
        }

        //DELETE AT
        if(\Illuminate\Support\Facades\Schema::hasColumn($data_schema, 'deleted_at')){
            $dynamic_table->whereRaw(' deleted_at IS NULL ' );
        }

        if($request->search_text && trim($request->search_text)){
            $search_text = '%'.str_replace(' ','%', trim($request->search_text)).'%';
            $dynamic_table->whereRaw(' name like ? ', [$search_text]);
        }

        $dynamic_table->select('id', 'name as text','name');

        return $dynamic_table->paginate(10);
    }

    public function list_selection(Request $request){
        $deviceList = PayModelHelper::get(DeviceAccess::class, $request, []);

        if($request->search_text){
            $search_text = '%'.str_replace(' ', '%', $request->search_text).'%';
           $deviceList->whereRaw(' CONCAT(type,name,user,port) LIKE ?', [$search_text]); 
        }

        if($request->only_active == 'yes'){
            $deviceList->where('is_active', 1);
        }

        $deviceList->select('id', \DB::raw("CONCAT(name, ' - [ ', type, ' ]') as text"), 'is_active');


        return $deviceList->paginate(10);

    }

    public function device_connection_check( Request $request, array $data){

        if($data['type'] == "mikrotik"){
            $result =  MikrotikHelper::credential_login_check($data);
            return $result;
        }
        else if($data['type'] == "ssh"){
            $result =  SshHelper::credential_login_check($data);
            
            if($result["status"] == 0 ){
                return ["status"=>0, "message"=>$result["message"]];
            }
            return $result;
            
        }

        return ["status"=>0, "message"=>"Type [".$data['type']."] not supported for checking yet."];
    }

    public function add(Request $request){

        //VALIDATIONS
        //Check if exists constrain by group_id and name

        $data = $this->validate($request, [
            "name"=>"required",
            "host"=>"required",
            "user"=>"required",
            "port"=>"required",
            "type"=>"required",
            "description"=>"nullable",
            "branch_ids"=>"required",
            "password"=>"nullable",
            "is_active"=>"required",
            "is_app_execute"=>"required",
            "is_ssl"=>"required"
        ])->validate();

        $exists = PayModelHelper::get(DeviceAccess::class, $request, [
            "name"=>$request->name
        ])->first();

        if(!in_array($request->type, ["mikrotik", "windows", "ssh", "ngteco-biometrics", "zkteco-biometrics"])){
            return [ "status"=>0, "message"=>"Device Type is not supported" ];
        }

        if($exists){
            return ["status"=>0, "message"=>"Device Custom Name already exists"];
        }

        
        $log = PayModelHelper::create(DeviceAccessTriggerLog::class, $request, [
            "target_name"=>$data['type']."-connect",
            "target_id"=>0,
            "device_access_id"=>0,
            "command"=>"",
            "response"=>"",
            "log_info"=>""
        ]);

        //CHECKING DEVICE SUCCESS LOGIN ACCOUNT VALIDATION
        if($request->is_check_before_saving){ 
            
            //IF FAILED
            $result = $this->device_connection_check( $request, $data);

            //COMMON RESULT
            if(isset($result['command']))
                $log->command = $result['command'];
            $log->response = $result['message'];

            if($result["status"] == 0){
            
                $log->status_id = 2;
                $log->log_info = "Checking credential failed..";
                $log->save();
                
                return $result;
            } 
            $log->status_id = 1;
            $log->log_info = "Checking credential successful..";
        }
        else{

            $log->log_info = "Bypass connection checking..";

        }

        if(!$data["password"]){
            $data["password"] = "";
        }

        //ADDING
        $device_access = PayModelHelper::create(DeviceAccess::class, $request, $data);
        
        if( isset($log) ){
            $log->device_access_id = $device_access->id;
            $log->save();
        }


        return ["status"=>1, "message"=>"Successfully Added.", "data"=>$device_access, "data_id"=>$device_access->id];
    }

    public function get(Request $request){
        
        //CHECK IF NAME EXISTTED
        $requiredId = $this->validate($request, [
            "device_access_id"=>"required"
        ])->validate();

        //VALIDATE NAME EXCEPT THE ID
        $device_access_id = $requiredId['device_access_id'];

        $can_manage = PayModelHelper::get(DeviceAccess::class, $request,[])->find($device_access_id);
        
        return $can_manage;
        
    }


    public function save(Request $request){
        
        //CHECK IF NAME EXISTTED
        $requiredId = $this->validate($request, [
            "device_access_id"=>"required"
        ])->validate();

        //VALIDATE NAME EXCEPT THE ID
        $device_access_id = $requiredId['device_access_id'];

        //CHECK IF YOU CAN MANAGE THE ID
        $can_manage = PayModelHelper::get(DeviceAccess::class, $request, [])->find($device_access_id);
        if(!$can_manage){
            return ["status"=>0, "message"=>"Access Device Forbidden access"];
        }

        $data = $this->validate($request, [
            "name"=>"required",
            "host"=>"required",
            "user"=>"required",
            "port"=>"required",
            "type"=>"required",
            "description"=>"nullable",
            "branch_ids"=>"required",
            "password"=>"nullable",
            "is_active"=>"required",
            "is_app_execute"=>"required",
            "is_ssl"=>"required"
        ])->validate();
        
        if(!in_array($request->type, ["mikrotik", "windows", "ssh", "ngteco-biometrics", "zkteco-biometrics"])){
            return [ "status"=>0, "message"=>"Device Type is not supported" ];
        }

        //CHECK IF ALREADY EXISTS
        $exists = PayModelHelper::get(DeviceAccess::class, $request,[])->whereRaw(' name = ? AND id NOT IN(?) ',[$data['name'], $device_access_id])->first();
        if($exists){
            return["status"=>0, "message"=>"Device custom name already exists"];
        }

        //Render password form existing.
        if(!$data['password']){
            $data['password'] = $can_manage['password'];
        }
        
        
        $log = PayModelHelper::create(DeviceAccessTriggerLog::class, $request, [
            "target_name"=>$data['type']."-connect",
            "target_id"=>0,
            "device_access_id"=>$can_manage->id,
            "command"=>"",
            "response"=>"",
            "log_info"=>""
        ]);


        //CHECKING DEVICE SUCCESS LOGIN ACCOUNT VALIDATION
        if($request->is_check_before_saving){ 
            //IF FAILED
            $result = $this->device_connection_check( $request, $data);
            
            //COMMON RESULT
            if(isset($result['command']))
                $log->command = $result['command'];
            $log->response = $result['message'];

            if($result["status"] == 0){
            
                $log->log_info = "Checking credential failed..";
                $log->status_id = 2;
                $log->save();
                
                return $result;
            } 
            $log->status_id = 1;
            $log->log_info = "Checking credential successful..";
        }
        else{

            $log->log_info = "Bypass connection checking..";

        }

        PayModelHelper::update($can_manage, $request, $data);

        if( isset($log) ){
            $log->device_access_id = $can_manage->id;
            $log->save();
        }


        return ["status"=>1, "message"=>"Successfully updated", "data"=>$can_manage];

    }

    public function remove(Request $request){
        
        //CHECK IF NAME EXISTTED
        $requiredId = $this->validate($request, [
            "device_access_id"=>"required"
        ])->validate();

        //VALIDATE NAME EXCEPT THE ID
        $device_access_id = $requiredId['device_access_id'];

        //CHECK IF YOU CAN MANAGE THE ID
        $can_manage = PayModelHelper::get(DeviceAccess::class, $request, [])->find($device_access_id);
        if(!$can_manage){
            return ["status"=>0, "message"=>"Access Device Forbidden access"];
        }
        PayModelHelper::delete($can_manage, $request);
        
        return ["status"=>1, "message"=>"Successfully removed."];

    }
}
