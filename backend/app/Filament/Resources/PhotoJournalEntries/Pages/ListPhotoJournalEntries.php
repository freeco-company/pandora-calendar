<?php

namespace App\Filament\Resources\PhotoJournalEntries\Pages;

use App\Filament\Resources\PhotoJournalEntries\PhotoJournalEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListPhotoJournalEntries extends ListRecords
{
    protected static string $resource = PhotoJournalEntryResource::class;
}
