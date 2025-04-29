<?php

namespace iProtek\Device\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceAccessTriggerLog extends Model
{
    use HasFactory;

    protected $fillable = [
        "device_access_id",
        "command",
        "response",
        "log_info",
        "target_name",
        "target_id",
        "status_id", //0 -pending, 1-success, 2-failed
        "device_template_trigger_id",
        "device_template_trigger_action",

        "group_id",
        "pay_created_by",
        "pay_updated_by",
        "pay_deleted_by"
    ];


    protected $casts = [
        "created_at"=>"datetime:F j, Y h:i A"
    ];


}
