<?php

namespace App\Filament\Resources\Feedbacks;

use App\Filament\Resources\Feedbacks\Pages\ListFeedbacks;
use App\Models\Feedback;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $navigationLabel = '使用者反饋';

    protected static ?string $modelLabel = '反饋';

    protected static ?string $pluralModelLabel = '反饋';

    protected static string|UnitEnum|null $navigationGroup = '用戶反饋';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

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
                TextColumn::make('category')->label('分類')->badge()->colors([
                    'danger' => 'bug', 'success' => 'feature',
                    'warning' => 'content', 'gray' => 'other',
                ])->sortable(),
                TextColumn::make('message')->label('內容')->limit(80)->searchable(),
                TextColumn::make('app_version')->label('版本')->toggleable(),
                IconColumn::make('processed_at')
                    ->label('已處理')
                    ->boolean()
                    ->getStateUsing(fn (Feedback $r) => $r->processed_at !== null),
                TextColumn::make('created_at')->label('建立')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('category')->label('分類')->options([
                    'bug' => 'Bug', 'feature' => '功能建議',
                    'content' => '內容', 'other' => '其他',
                ]),
                Filter::make('unprocessed')->label('僅看未處理')
                    ->query(fn (Builder $q) => $q->whereNull('processed_at')),
            ])
            ->recordActions([
                Action::make('toggle_processed')
                    ->label(fn (Feedback $r) => $r->processed_at ? '取消處理' : '標記已處理')
                    ->icon(fn (Feedback $r) => $r->processed_at ? 'heroicon-m-arrow-uturn-left' : 'heroicon-m-check')
                    ->color(fn (Feedback $r) => $r->processed_at ? 'gray' : 'success')
                    ->action(fn (Feedback $r) => $r->update([
                        'processed_at' => $r->processed_at ? null : now(),
                    ])),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label('匯出 CSV')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(fn () => static::exportCsv()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_processed')->label('批次標記已處理')->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['processed_at' => now()])),
                ]),
            ]);
    }

    /**
     * Streams a CSV of all feedback rows. Memory-light: lazy() iterator, never
     * materializes the full set in PHP.
     */
    public static function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'user_id', 'category', 'message', 'app_version', 'processed_at', 'created_at']);
            Feedback::query()->orderBy('id')->lazy()->each(function (Feedback $f) use ($out): void {
                fputcsv($out, [
                    $f->id, $f->user_id, $f->category, $f->message,
                    $f->app_version, $f->processed_at?->toIso8601String(),
                    $f->created_at?->toIso8601String(),
                ]);
            });
            fclose($out);
        }, 'feedbacks-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedbacks::route('/'),
        ];
    }
}
