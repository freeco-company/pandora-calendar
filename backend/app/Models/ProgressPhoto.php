<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'storage_path', 'phase_at_capture', 'captured_on', 'note'];

    protected $casts = ['captured_on' => 'date:Y-m-d'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
