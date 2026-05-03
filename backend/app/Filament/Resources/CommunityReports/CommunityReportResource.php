<?php

namespace App\Filament\Resources\CommunityReports;

use App\Filament\Resources\CommunityReports\Pages\ListCommunityReports;
use App\Models\CommunityPost;
use App\Models\CommunityReply;
use App\Models\CommunityReport;
use App\Services\Admin\ModerationActionLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CommunityReportResource extends Resource
{
    protected static ?string $model = CommunityReport::class;

    protected static ?string $navigationLabel = '檢舉收件匣';

    protected static ?string $modelLabel = '檢舉';

    protected static ?string $pluralModelLabel = '檢舉';

    protected static string|UnitEnum|null $navigationGroup = '社群審查';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 0; // top: pending reports first

    public static function getNavigationBadge(): ?string
    {
        $pending = CommunityReport::query()->whereNull('resolved_at')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        // Reports are read-only — admin acts via row actions, not form edits.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('target_type')->label('目標類型')->badge(),
                TextColumn::make('target_id')->label('目標 ID'),
                TextColumn::make('reason')->label('原因')->badge(),
                TextColumn::make('message')->label('留言')->limit(40),
                TextColumn::make('reporter_user_id')->label('檢舉人')->toggleable(),
                TextColumn::make('resolved_at')->label('處理時間')->dateTime('Y-m-d H:i')->placeholder('— 未處理 —'),
                TextColumn::make('created_at')->label('建立')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->orderByRaw('resolved_at IS NULL DESC'))
            ->filters([
                Filter::make('pending')->label('僅看待處理')
                    ->query(fn (Builder $q) => $q->whereNull('resolved_at'))
                    ->default(),
                SelectFilter::make('target_type')->label('目標類型')->options([
                    'post' => 'Post',
                    'reply' => 'Reply',
                ]),
            ])
            ->recordActions([
                Action::make('resolve_no_violation')
                    ->label('無違規')->color('gray')->icon('heroicon-m-check')
                    ->action(function (CommunityReport $record): void {
                        $record->update(['resolved_at' => now()]);
                        ModerationActionLogger::log(
                            $record->target_type, (int) $record->target_id,
                            'approve', auth()->id(), 'report dismissed (no violation)',
                        );
                    }),
                Action::make('remove_target')
                    ->label('移除目標')->color('danger')->icon('heroicon-m-trash')
                    ->requiresConfirmation()
                    ->action(function (CommunityReport $record): void {
                        $model = $record->target_type === 'reply'
                            ? CommunityReply::class
                            : CommunityPost::class;
                        if ($target = $model::find($record->target_id)) {
                            $target->update(['status' => 'removed']);
                        }
                        $record->update(['resolved_at' => now()]);
                        ModerationActionLogger::log(
                            $record->target_type, (int) $record->target_id,
                            'remove', auth()->id(), 'removed via report inbox',
                        );
                    }),
                Action::make('warn_user')
                    ->label('警告作者')->color('warning')->icon('heroicon-m-exclamation-triangle')
                    ->action(function (CommunityReport $record): void {
                        // Phase 0: log only — no in-app notification yet.
                        // FCM warn flow lands in Phase 4 (HANDOFF.md cap).
                        $record->update(['resolved_at' => now()]);
                        ModerationActionLogger::log(
                            $record->target_type, (int) $record->target_id,
                            'flag', auth()->id(), 'user warned (logged, not pushed)',
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityReports::route('/'),
        ];
    }
}
