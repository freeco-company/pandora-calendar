<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photo journal entry — metadata only。
 *
 * 紅線：本 model 永遠 **不持有 binary**。binary 在 device（cloud_synced=false 時）
 * 或 private signed URL（Premium cloud sync）。任何 controller / service 不可
 * `Storage::put` 進公開 disk。
 */
class PhotoJournalEntry extends Model
{
    use HasFactory;

    public const TAG_FACE = 'face';
    public const TAG_BODY = 'body';
    public const TAG_NOTE = 'note';

    public const TAGS = [self::TAG_FACE, self::TAG_BODY, self::TAG_NOTE];

    protected $fillable = [
        'user_id',
        'phase',
        'cycle_day',
        'tag',
        'note_text',
        'local_path',
        'cloud_synced',
        'cloud_url',
        'cloud_object_key',
        'thumb_blurhash',
        'captured_on',
    ];

    protected $casts = [
        'captured_on' => 'date:Y-m-d',
        'cycle_day' => 'integer',
        'cloud_synced' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
