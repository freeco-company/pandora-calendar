<?php

namespace App\Filament\Resources\QnaQuestions\Pages;

use App\Filament\Resources\QnaQuestions\QnaQuestionResource;
use Filament\Resources\Pages\ListRecords;

class ListQnaQuestions extends ListRecords
{
    protected static string $resource = QnaQuestionResource::class;
}
