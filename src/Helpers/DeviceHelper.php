<?php
namespace iProtek\Device\Helpers;

use DB; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use iProtek\Device\Helpers\DeviceVariableHelper;
use iProtek\Device\Models\DeviceTemplateTrigger;
use iProtek\Device\Models\DeviceAccess;

class DeviceHelper
{ 
    /**
     * [account field="id" ] - get the data id
     * [device_account_id] - get the account id from the device upon registration.
     * [account field="plan" ] - get the "plan" field value form target source model.
     * [account field="User Name" data-json="json"] - get the "User Name" field value form target source custom fields. 
     */
    public static function translate_template($template,  $target_name, $target_id ){
        $data = \DB::table($target_name)->where('id', $target_id)->first();
        if(!$data){
            return ["status"=>0, "message"=>"Data Not Found", "template"=>$template];
        }

        //ACCOUNT
        //ACCOUNT TEMPLATE
        $translate = DeviceVariableHelper::account($template, $data, $target_name, $target_id);

        //DEVICE ACCOUNT ID
        $translate = DeviceVariableHelper::device_account_id($translate, $target_name, $target_id); 

        return $translate;
         

    }

    public static function allow_template_triggers($branch_id, $target_name, $target_id){

        //TODO: branch_id and existing account
        $deviceAccessIds = DeviceAccess::whereRaw(' JSON_CONTAINS( branch_ids, ? ) ', [$branch_id])->get()->pluck('id')->toArray();

        //DeviceTemplateTrigger
        //CHECK IF ALLOWED
        $templateList = DeviceTemplateTrigger::whereIn('device_access_id', $deviceAccessIds)
            ->where('target_name', $target_name)->with(['target_params'])->get();
        $listAllowedIds = [];
        
        if(count($templateList) < 0)
            return [];

        foreach($templateList as $temp){
            $params = [];

            foreach($temp->target_params as $par){
                $params[$par->field_name] = $par->value;
            }
            if(count($params)){
                $hasExist = \DB::table($temp->target_name)->where('id',$target_id)->where($params)->first();
                if($hasExist){
                    $listAllowedIds[] = $temp->id;
                }
            }
            else{
                $listAllowedIds[] = $temp->id;
            }
        }
        return $listAllowedIds;

    }
}