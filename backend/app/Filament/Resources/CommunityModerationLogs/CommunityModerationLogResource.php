<?php

namespace App\Filament\Resources\CommunityModerationLogs;

use App\Filament\Resources\CommunityModerationLogs\Pages\ListCommunityModerationLogs;
use App\Models\CommunityModerationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CommunityModerationLogResource extends Resource
{
    protected static ?string $model = CommunityModerationLog::class;

    protected static ?string $navigationLabel = '審查紀錄';

    protected static ?string $modelLabel = '審查紀錄';

    protected static ?string $pluralModelLabel = '審查紀錄';

    protected static string|UnitEnum|null $navigationGroup = '社群審查';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('target_type')->label('目標類型')->badge(),
                TextColumn::make('target_id')->label('目標 ID'),
                TextColumn::make('action')->label('動作')->badge()->colors([
                    'gray' => 'auto_block',
                    'warning' => ['flag', 'hide'],
                    'success' => 'approve',
                    'danger' => 'remove',
                ]),
                TextColumn::make('moderator_user_id')->label('moderator')->placeholder('— 系統 —'),
                TextColumn::make('reason')->label('原因')->limit(50),
                TextColumn::make('created_at')->label('時間')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->filters([
                SelectFilter::make('action')->label('動作')->options([
                    'auto_block' => 'auto_block',
                    'flag' => 'flag',
                    'approve' => 'approve',
                    'hide' => 'hide',
                    'remove' => 'remove',
                ]),
                SelectFilter::make('target_type')->label('目標類型')->options([
                    'post' => 'Post',
                    'reply' => 'Reply',
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityModerationLogs::route('/'),
        ];
    }
}
