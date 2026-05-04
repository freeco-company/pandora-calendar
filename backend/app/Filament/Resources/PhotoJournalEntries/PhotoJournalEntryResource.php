<?php

namespace App\Filament\Resources\PhotoJournalEntries;

use App\Filament\Resources\PhotoJournalEntries\Pages\ListPhotoJournalEntries;
use App\Filament\Resources\PhotoJournalEntries\Pages\ViewPhotoJournalEntry;
use App\Models\PhotoJournalEntry;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Photo journal — admin 端純 metadata view，**禁止**顯示 binary / cloud_url / note_text。
 *
 * 隱私三層守住點（CLAUDE.md / migration 紅線）：
 *   (1) 列表只給 user_id / tag / phase / cycle_day / cloud_synced / captured_on / created_at
 *   (2) 詳情頁排除 note_text / cloud_url / cloud_object_key / local_path / thumb_blurhash
 *   (3) 沒有 form / edit / delete — admin 只 count + 統計，不能看 / 不能改用戶私密內容
 *
 * 用途：內部 capacity / usage 分析（多少用戶用 face vs body tag、cloud sync 比例 etc.）。
 * 個別 inspect 應交由用戶自助（GDPR-style）。
 */
class PhotoJournalEntryResource extends Resource
{
    protected static ?string $model = PhotoJournalEntry::class;

    protected static ?string $navigationLabel = '進度照（純統計）';

    protected static ?string $modelLabel = '進度照 metadata';

    protected static ?string $pluralModelLabel = '進度照 metadata';

    protected static string|UnitEnum|null $navigationGroup = '進階管理';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * Privacy-safe infolist。
     *
     * 嚴格不放：note_text、cloud_url、cloud_object_key、local_path、thumb_blurhash。
     * 違反任一條 = 隱私紅線爆掉。
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id')->label('ID'),
            TextEntry::make('user_id')->label('User'),
            TextEntry::make('tag')->label('類型')->badge(),
            TextEntry::make('phase')->label('週期相位')->placeholder('—'),
            TextEntry::make('cycle_day')->label('週期第幾天')->placeholder('—'),
            TextEntry::make('cloud_synced')->label('已同步雲端')->badge()
                ->color(fn ($state) => $state ? 'info' : 'gray')
                ->formatStateUsing(fn ($state) => $state ? '是' : '否'),
            TextEntry::make('captured_on')->label('拍攝日')->date(),
            TextEntry::make('created_at')->label('建立')->dateTime('Y-m-d H:i'),
            TextEntry::make('privacy_notice')
                ->label('隱私說明')
                ->state('依紅線守則，admin 不顯示 note 內容、雲端連結、blurhash 或檔案路徑。')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user_id')->label('User')->searchable()->sortable(),
                TextColumn::make('tag')
                    ->label('類型')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'face' => 'info',
                        'body' => 'warning',
                        'note' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('phase')->label('相位')->toggleable(),
                TextColumn::make('cycle_day')->label('週期 day')->toggleable()->sortable(),
                IconColumn::make('cloud_synced')->label('雲端')->boolean(),
                TextColumn::make('captured_on')->label('拍攝日')->date('Y-m-d')->sortable(),
                TextColumn::make('created_at')->label('建立')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('tag')
                    ->label('類型')
                    ->options([
                        'face' => 'Face',
                        'body' => 'Body',
                        'note' => 'Note',
                    ]),
                TernaryFilter::make('cloud_synced')
                    ->label('已同步雲端')
                    ->trueLabel('是')
                    ->falseLabel('否')
                    ->placeholder('全部'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPhotoJournalEntries::route('/'),
            'view' => ViewPhotoJournalEntry::route('/{record}'),
        ];
    }
}
