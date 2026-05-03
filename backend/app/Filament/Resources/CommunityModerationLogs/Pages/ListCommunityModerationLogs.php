<?php

namespace App\Filament\Resources\CommunityModerationLogs\Pages;

use App\Filament\Resources\CommunityModerationLogs\CommunityModerationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityModerationLogs extends ListRecords
{
    protected static string $resource = CommunityModerationLogResource::class;
}
