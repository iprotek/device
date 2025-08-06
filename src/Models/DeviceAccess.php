<?php

namespace iProtek\Device\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceAccess extends Model
{
    use HasFactory, SoftDeletes;

    public $fillable = [
        "group_id",
        "pay_created_by",
        "pay_updated_by",
        "pay_deleted_by",

        "type",
        "name",
        "description",
        "host",
        "user",
        "password",
        "port",
        "branch_ids",
        "branch_source",
        "is_active",
        "is_error",
        "is_app_execute",
        "error_info",
        "is_ssl",
        "is_trigger_registration"
    ];

    /*
    protected $hidden = [
        "password"
    ];
    */

    protected $casts = [
        "branch_ids"=> "json",
        "is_active" => "boolean",
        "is_error"  => "boolean",
        "is_app_execute" => "boolean",
        "is_ssl" => "boolean",
        "is_trigger_registration"=>"boolean"
    ];
}
