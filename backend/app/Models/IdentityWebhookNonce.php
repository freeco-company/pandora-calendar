<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityWebhookNonce extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_id', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];
}
