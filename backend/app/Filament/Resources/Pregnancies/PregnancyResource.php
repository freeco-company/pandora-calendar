<?php

namespace App\Filament\Resources\Pregnancies;

use App\Filament\Resources\Pregnancies\Pages\ListPregnancies;
use App\Filament\Resources\Pregnancies\Pages\ViewPregnancy;
use App\Models\Pregnancy;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 孕期紀錄 — admin 端用於支援關懷工作。
 *
 * 紅線（人本主義）：
 *   - miscarriage / cancelled / false_alarm 案例 admin 應主動人工關懷
 *   - 列表用色塊讓 ended_reason = miscarriage 直接視覺凸顯
 *   - 唯讀（admin 不可直接改孕期狀態，必須由用戶端 self-service）
 */
class PregnancyResource extends Resource
{
    protected static ?string $model = Pregnancy::class;

    protected static ?string $navigationLabel = '孕期紀錄';

    protected static ?string $modelLabel = '孕期紀錄';

    protected static ?string $pluralModelLabel = '孕期紀錄';

    protected static string|UnitEnum|null $navigationGroup = '進階管理';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id')->label('ID'),
            TextEntry::make('user_id')->label('User'),
            TextEntry::make('status')->label('狀態')->badge()->color(fn (string $state) => match ($state) {
                'active' => 'success',
                'paused' => 'warning',
                'ended' => 'gray',
                default => 'gray',
            }),
            TextEntry::make('lmp_date')->label('LMP（最後一次月經）')->date(),
            TextEntry::make('estimated_due_date')->label('預產期')->date(),
            TextEntry::make('ended_on')->label('結束日')->date()->placeholder('—'),
            TextEntry::make('ended_reason')
                ->label('結束原因')
                ->badge()
                ->color(fn (?string $state) => match ($state) {
                    'birth' => 'success',
                    'miscarriage' => 'danger',
                    'cancelled', 'false_alarm' => 'warning',
                    default => 'gray',
                })
                ->placeholder('—'),
            TextEntry::make('mode_started_at')->label('進入孕期模式時間')->dateTime('Y-m-d H:i'),
            KeyValueEntry::make('milestones')
                ->label('Milestones')
                ->columnSpanFull(),
            TextEntry::make('created_at')->label('建立')->dateTime('Y-m-d H:i'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user_id')->label('User')->searchable()->sortable(),
                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'ended' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('lmp_date')->label('LMP')->date('Y-m-d')->sortable(),
                TextColumn::make('estimated_due_date')->label('預產期')->date('Y-m-d')->sortable(),
                TextColumn::make('ended_reason')
                    ->label('結束原因')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'birth' => 'success',
                        'miscarriage' => 'danger',
                        'cancelled', 'false_alarm' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('created_at')->label('建立')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'ended' => 'Ended',
                    ]),
                SelectFilter::make('ended_reason')
                    ->label('結束原因')
                    ->options([
                        'birth' => '生產',
                        'miscarriage' => '流產（須關懷）',
                        'cancelled' => '取消',
                        'false_alarm' => '虛驚',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            // miscarriage 案例紅 highlight，提醒 admin 主動關懷
            ->recordClasses(fn (Pregnancy $r) => $r->ended_reason === 'miscarriage'
                ? 'bg-red-50 dark:bg-red-950/30'
                : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPregnancies::route('/'),
            'view' => ViewPregnancy::route('/{record}'),
        ];
    }
}
