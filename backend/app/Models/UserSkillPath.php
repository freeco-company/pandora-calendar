<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkillPath extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id', 'path', 'chosen_at', 'last_changed_at', 'progress_json',
    ];

    protected $casts = [
        'chosen_at' => 'datetime',
        'last_changed_at' => 'datetime',
        'progress_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
