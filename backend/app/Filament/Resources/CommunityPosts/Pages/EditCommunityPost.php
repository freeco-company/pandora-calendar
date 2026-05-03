<?php

namespace App\Filament\Resources\CommunityPosts\Pages;

use App\Filament\Resources\CommunityPosts\CommunityPostResource;
use App\Services\Admin\ModerationActionLogger;
use Filament\Resources\Pages\EditRecord;

class EditCommunityPost extends EditRecord
{
    protected static string $resource = CommunityPostResource::class;

    /**
     * On status edit, write a moderation log so we have a full audit trail.
     * `admin_reason` is a non-persisted form field captured here.
     */
    protected function afterSave(): void
    {
        $reason = $this->data['admin_reason'] ?? null;
        $action = match ($this->record->status) {
            'hidden' => 'hide',
            'removed' => 'remove',
            'published' => 'approve',
            default => 'flag',
        };
        ModerationActionLogger::log(
            'post',
            (int) $this->record->id,
            $action,
            auth()->id(),
            $reason,
        );
    }
}
