<?php

namespace App\Filament\Resources\CommunityReplies\Pages;

use App\Filament\Resources\CommunityReplies\CommunityReplyResource;
use App\Services\Admin\ModerationActionLogger;
use Filament\Resources\Pages\EditRecord;

class EditCommunityReply extends EditRecord
{
    protected static string $resource = CommunityReplyResource::class;

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
            'reply',
            (int) $this->record->id,
            $action,
            auth()->id(),
            $reason,
        );
    }
}
