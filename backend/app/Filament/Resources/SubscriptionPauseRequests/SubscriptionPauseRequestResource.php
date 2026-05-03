<?php

namespace App\Filament\Resources\SubscriptionPauseRequests;

use App\Filament\Resources\SubscriptionPauseRequests\Pages\ListSubscriptionPauseRequests;
use App\Models\SubscriptionPauseRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class SubscriptionPauseRequestResource extends Resource
{
    protected static ?string $model = SubscriptionPauseRequest::class;

    protected static ?string $navigationLabel = '訂閱暫停 / 取消';

    protected static ?string $modelLabel = '取消申請';

    protected static ?string $pluralModelLabel = '取消申請';

    protected static string|UnitEnum|null $navigationGroup = '訂閱';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pause-circle';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user_id')->label('user_id')->toggleable(),
                TextColumn::make('reason')->label('取消原因')->badge()->searchable(),
                TextColumn::make('pause_months')->label('暫停月數')->sortable(),
                TextColumn::make('granted_discount_percent')->label('給折扣 %')->sortable(),
                TextColumn::make('granted_pause_until')->label('暫停至')->date('Y-m-d')->sortable(),
                TextColumn::make('created_at')->label('申請時間')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('reason')->label('原因')->options([
                    'too_expensive' => '太貴',
                    'not_useful' => '沒用到',
                    'pregnant' => '懷孕了',
                    'other' => '其他',
                ]),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label('匯出 CSV')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(fn () => static::exportCsv()),
            ]);
    }

    public static function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'user_id', 'reason', 'pause_months', 'granted_discount_percent', 'granted_pause_until', 'created_at']);
            SubscriptionPauseRequest::query()->orderBy('id')->lazy()->each(function (SubscriptionPauseRequest $r) use ($out): void {
                fputcsv($out, [
                    $r->id, $r->user_id, $r->reason, $r->pause_months,
                    $r->granted_discount_percent,
                    $r->granted_pause_until?->toDateString(),
                    $r->created_at?->toIso8601String(),
                ]);
            });
            fclose($out);
        }, 'pause-requests-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPauseRequests::route('/'),
        ];
    }
}
