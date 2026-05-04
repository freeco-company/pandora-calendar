<?php

namespace App\Filament\Resources\DodoCoinTransactions;

use App\Filament\Resources\DodoCoinTransactions\Pages\ListDodoCoinTransactions;
use App\Models\DodoCoinTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * 朵朵幣交易帳本 — 用於追查經濟異常。
 *
 * 紅線：朵朵幣只能賺、不能買；若看到單用戶 balance_after 異常飆升 = 可能 abuse / bug。
 * Resource 為唯讀（不提供 form），admin 想調整 balance 必須走 service layer 留 audit log。
 */
class DodoCoinTransactionResource extends Resource
{
    protected static ?string $model = DodoCoinTransaction::class;

    protected static ?string $navigationLabel = '朵朵幣交易帳本';

    protected static ?string $modelLabel = '朵朵幣交易';

    protected static ?string $pluralModelLabel = '朵朵幣交易';

    protected static string|UnitEnum|null $navigationGroup = '進階管理';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user_id')->label('User')->searchable()->sortable(),
                TextColumn::make('delta')
                    ->label('變動量')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state) => ($state >= 0 ? '+' : '') . number_format($state)),
                TextColumn::make('source')
                    ->label('來源')
                    ->badge()
                    ->searchable(),
                TextColumn::make('balance_after')
                    ->label('交易後 balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('建立')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('source')
                    ->label('來源')
                    ->options([
                        'daily_action' => 'Daily Action',
                        'streak' => 'Streak',
                        'achievement' => 'Achievement',
                        'random_event' => 'Random Event',
                        'solar_term' => 'Solar Term',
                        'refund' => 'Refund',
                        'spend_outfit' => 'Spend: Outfit',
                        'spend_story_chapter' => 'Spend: Story Chapter',
                        'spend_pet_item' => 'Spend: Pet Item',
                        'spend_other' => 'Spend: Other',
                    ]),
                SelectFilter::make('user_id')
                    ->label('User ID')
                    ->relationship('user', 'id')
                    ->searchable()
                    ->preload(),
                Filter::make('last_7_days')
                    ->label('最近 7 天')
                    ->query(fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(7))),
                Filter::make('last_30_days')
                    ->label('最近 30 天')
                    ->query(fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(30))),
                Filter::make('earn_only')
                    ->label('僅看 earn')
                    ->query(fn (Builder $q) => $q->where('delta', '>', 0)),
                Filter::make('spend_only')
                    ->label('僅看 spend')
                    ->query(fn (Builder $q) => $q->where('delta', '<', 0)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDodoCoinTransactions::route('/'),
        ];
    }
}
