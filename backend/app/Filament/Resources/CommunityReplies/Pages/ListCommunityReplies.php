<?php

namespace App\Filament\Resources\CommunityReplies\Pages;

use App\Filament\Resources\CommunityReplies\CommunityReplyResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityReplies extends ListRecords
{
    protected static string $resource = CommunityReplyResource::class;
}
