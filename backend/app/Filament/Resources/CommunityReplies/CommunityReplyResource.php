<?php

namespace App\Filament\Resources\CommunityReplies;

use App\Filament\Resources\CommunityReplies\Pages\EditCommunityReply;
use App\Filament\Resources\CommunityReplies\Pages\ListCommunityReplies;
use App\Models\CommunityReply;
use App\Services\Admin\ModerationActionLogger;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class CommunityReplyResource extends Resource
{
    protected static ?string $model = CommunityReply::class;

    protected static ?string $navigationLabel = '社群回覆';

    protected static ?string $modelLabel = '社群回覆';

    protected static ?string $pluralModelLabel = '社群回覆';

    protected static string|UnitEnum|null $navigationGroup = '社群審查';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('id')->disabled(),
            TextInput::make('post_id')->disabled(),
            TextInput::make('user_id')->label('user_id（admin 視野）')->disabled(),
            TextInput::make('anonymous_handle')->disabled(),
            Textarea::make('body')->disabled()->rows(6)->columnSpanFull(),
            Select::make('status')->label('狀態')->options([
                'pending' => '待審', 'published' => '已發佈',
                'hidden' => '已隱藏', 'removed' => '已移除',
            ])->required(),
            TextInput::make('moderation_score')->disabled()->numeric(),
            TextInput::make('reported_count')->disabled()->numeric(),
            Textarea::make('admin_reason')->label('調整原因')->dehydrated(false)->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('post_id')->label('Post')->sortable(),
                TextColumn::make('anonymous_handle')->label('匿名暱稱')->searchable(),
                IconColumn::make('is_dodo')->label('朵朵')->boolean(),
                TextColumn::make('body')->label('內容')->limit(60)->searchable(),
                TextColumn::make('status')->label('狀態')->badge()->colors([
                    'gray' => 'pending', 'success' => 'published',
                    'warning' => 'hidden', 'danger' => 'removed',
                ])->sortable(),
                TextColumn::make('moderation_score')->label('AI 風險')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('reported_count')->label('被檢舉')->sortable(),
                TextColumn::make('created_at')->label('建立')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('狀態')->options([
                    'pending' => '待審', 'published' => '已發佈',
                    'hidden' => '已隱藏', 'removed' => '已移除',
                ]),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hide')->label('批次隱藏')->color('warning')
                        ->action(function (Collection $records): void {
                            foreach ($records as $r) {
                                $r->update(['status' => 'hidden']);
                                ModerationActionLogger::log('reply', (int) $r->id, 'hide', auth()->id(), 'bulk hide');
                            }
                        }),
                    BulkAction::make('remove')->label('批次移除')->color('danger')->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $r) {
                                $r->update(['status' => 'removed']);
                                ModerationActionLogger::log('reply', (int) $r->id, 'remove', auth()->id(), 'bulk remove');
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityReplies::route('/'),
            'edit' => EditCommunityReply::route('/{record}/edit'),
        ];
    }
}
