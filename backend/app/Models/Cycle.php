<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cycle extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'start_date', 'end_date', 'peak_flow', 'notes'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'peak_flow' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 經期長度（含起訖日）。
     *
     * Sanity guard：> 14 或 < 1 視為髒資料（end_date 在未來、手動 import 錯誤），回 null。
     * 真正驗證在 CycleController；此處只做 read-time 防呆，避免月曆 phase coloring 把 20 天全標經期。
     */
    public function lengthInDays(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        $days = $this->start_date->diffInDays($this->end_date) + 1;

        if ($days < 1 || $days > 14) {
            return null;
        }

        return $days;
    }
}
