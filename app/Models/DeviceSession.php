<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSession extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_type',
        'browser',
        'ip_address',
        'last_active_at',
    ];
}
