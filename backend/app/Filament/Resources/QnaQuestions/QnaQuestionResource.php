<?php

namespace App\Filament\Resources\QnaQuestions;

use App\Filament\Resources\QnaQuestions\Pages\ListQnaQuestions;
use App\Filament\Resources\QnaQuestions\Pages\ViewQnaQuestion;
use App\Models\QnaQuestion;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
 * Q&A 問答歷史 — admin 端做安全 review。
 *
 * 紅線：
 *   - safety_flag = redline_self_harm 必須由 admin 主動人工 review
 *   - safety_flag = redline_compliance 是 sanitizer 過濾的合規問題
 *   - bulk delete 用於清理過大歷史（user 端已 GDPR-style 自助刪除，admin 用於批量壓縮）
 */
class QnaQuestionResource extends Resource
{
    protected static ?string $model = QnaQuestion::class;

    protected static ?string $navigationLabel = 'Q&A 問答歷史';

    protected static ?string $modelLabel = 'Q&A 問題';

    protected static ?string $pluralModelLabel = 'Q&A 問題';

    protected static string|UnitEnum|null $navigationGroup = '進階管理';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        // Q&A 不能改內容（歷史紀錄不可篡改）；只給 view，所以 form 留空。
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id')->label('ID'),
            TextEntry::make('user_id')->label('User'),
            TextEntry::make('question')->label('問題')->columnSpanFull(),
            TextEntry::make('answer')->label('朵朵回答')->columnSpanFull(),
            TextEntry::make('llm_provider')->label('LLM Provider')->badge(),
            TextEntry::make('response_time_ms')->label('回應時間 (ms)'),
            TextEntry::make('safety_flag')
                ->label('Safety Flag')
                ->badge()
                ->color(fn (?string $state) => match ($state) {
                    'redline_self_harm' => 'danger',
                    'redline_compliance' => 'warning',
                    default => 'success',
                })
                ->placeholder('正常 / passed'),
            KeyValueEntry::make('sources')
                ->label('引用文章 ID')
                ->columnSpanFull(),
            TextEntry::make('created_at')->label('建立時間')->dateTime('Y-m-d H:i:s'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user_id')->label('User')->searchable()->sortable(),
                TextColumn::make('question')
                    ->label('問題')
                    ->limit(60)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('answer')
                    ->label('回答縮略')
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('safety_flag')
                    ->label('安全旗標')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'redline_self_harm' => 'danger',
                        'redline_compliance' => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ?? '正常'),
                TextColumn::make('llm_provider')
                    ->label('Provider')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('response_time_ms')
                    ->label('耗時 ms')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('建立')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('safety_flag')
                    ->label('安全旗標')
                    ->options([
                        'redline_self_harm' => '🚨 Self-harm（人工 review）',
                        'redline_compliance' => '⚠️ 合規違規',
                    ])
                    ->placeholder('全部（含正常）'),
                SelectFilter::make('llm_provider')
                    ->label('Provider')
                    ->options([
                        'openai' => 'OpenAI',
                        'claude' => 'Claude',
                        'blocked' => 'Blocked',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('批次刪除（壓縮歷史）')
                        ->requiresConfirmation(),
                ]),
            ])
            // 紅 highlight self-harm row
            ->recordClasses(fn (QnaQuestion $r) => $r->safety_flag === 'redline_self_harm'
                ? 'bg-red-50 dark:bg-red-950/30'
                : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQnaQuestions::route('/'),
            'view' => ViewQnaQuestion::route('/{record}'),
        ];
    }
}
