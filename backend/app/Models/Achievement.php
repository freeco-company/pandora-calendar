<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'achievement_key', 'unlocked_at'];

    protected $casts = ['unlocked_at' => 'datetime'];
}
