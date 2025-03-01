<?php

namespace iProtek\Device\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceAccount extends Model
{
    use HasFactory;
    
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
        "active_info"
    ];

    protected $casts = [
        "is_active" => "boolean"
    ];
}
