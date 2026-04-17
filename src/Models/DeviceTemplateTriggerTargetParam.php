<?php

namespace iProtek\Device\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceTemplateTriggerTargetParam extends Model
{
    //
    protected $fillable = [
        "group_id",
        "pay_created_by",
        "pay_updated_by",
        "pay_deleted_by",

        "device_template_trigger_id",
        "order_no",
        "field_name",
        "value"
    ];
}
