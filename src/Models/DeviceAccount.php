<?php

namespace iProtek\Device\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log; 
use iProtek\Core\Models\_CommonModel;
use Awobaz\Compoships\Compoships;

class DeviceAccount extends _CommonModel
{
    use HasFactory, Compoships;
    //use Compoships;
    
    public $fillable = [
        "group_id",
        "pay_created_by",
        "pay_updated_by",
        "pay_deleted_by",

        "device_template_trigger_id",
        "target_name",
        "target_id",
        "is_active",
        "account_id",
        "active_info",
        "is_auto_trigger"
    ];

    protected $casts = [
        "is_active" => "boolean",
        "is_auto_trigger" => "boolean",
        "created_at"=>"datetime:F j,Y h:i a",
        "updated_at"=>"datetime:F j,Y h:i a"
    ];

    public function device_template_trigger(){
        return $this->belongsTo(DeviceTemplateTrigger::class, 'device_template_trigger_id');
    }

    public function latest_action(){ 
            return $this->hasOne(DeviceAccessTriggerLog::class, ['target_id','target_name','device_template_trigger_id'], ['target_id','target_name','device_template_trigger_id']) 
            ->orderBy('is_resolved','ASC')->orderBy('id', 'desc');
    }
}
