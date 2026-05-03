<?php

namespace App\Filament\Resources\CommunityPosts;

use App\Filament\Resources\CommunityPosts\Pages\EditCommunityPost;
use App\Filament\Resources\CommunityPosts\Pages\ListCommunityPosts;
use App\Filament\Resources\CommunityPosts\Schemas\CommunityPostForm;
use App\Filament\Resources\CommunityPosts\Tables\CommunityPostsTable;
use App\Models\CommunityPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CommunityPostResource extends Resource
{
    protected static ?string $model = CommunityPost::class;

    protected static ?string $navigationLabel = '社群貼文';

    protected static ?string $modelLabel = '社群貼文';

    protected static ?string $pluralModelLabel = '社群貼文';

    protected static string|UnitEnum|null $navigationGroup = '社群審查';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CommunityPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityPosts::route('/'),
            'edit' => EditCommunityPost::route('/{record}/edit'),
        ];
    }
}
