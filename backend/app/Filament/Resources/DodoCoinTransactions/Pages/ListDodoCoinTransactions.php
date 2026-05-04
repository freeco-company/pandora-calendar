<?php

namespace App\Filament\Resources\DodoCoinTransactions\Pages;

use App\Filament\Resources\DodoCoinTransactions\DodoCoinTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListDodoCoinTransactions extends ListRecords
{
    protected static string $resource = DodoCoinTransactionResource::class;
}
