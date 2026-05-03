<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'p256dh', 'auth', 'platform', 'device_token', 'last_used_at'];

    protected $casts = ['last_used_at' => 'datetime'];
}
