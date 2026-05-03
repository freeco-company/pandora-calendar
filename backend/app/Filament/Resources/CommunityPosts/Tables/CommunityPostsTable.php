<?php

namespace App\Filament\Resources\CommunityPosts\Tables;

use App\Services\Admin\ModerationActionLogger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CommunityPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('anonymous_handle')->label('匿名暱稱')->searchable(),
                TextColumn::make('user_id')->label('user_id')->toggleable()->searchable(),
                TextColumn::make('category')->label('分類')->badge()->sortable(),
                TextColumn::make('title')->label('標題')->limit(40)->searchable(),
                TextColumn::make('status')->label('狀態')->badge()->colors([
                    'gray' => 'pending',
                    'success' => 'published',
                    'warning' => 'hidden',
                    'danger' => 'removed',
                ])->sortable(),
                TextColumn::make('moderation_score')->label('AI 風險分')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('reported_count')->label('被檢舉數')->sortable(),
                TextColumn::make('reply_count')->label('回覆')->sortable(),
                TextColumn::make('like_count')->label('讚')->sortable(),
                TextColumn::make('created_at')->label('建立')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('狀態')->options([
                    'pending' => '待審', 'published' => '已發佈',
                    'hidden' => '已隱藏', 'removed' => '已移除',
                ]),
                SelectFilter::make('category')->label('分類')->options([
                    'cycle' => '週期', 'mood' => '心情', 'tips' => '小訣竅', 'other' => '其他',
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function ($record, array $data): void {
                        ModerationActionLogger::log(
                            'post',
                            (int) $record->id,
                            match ($record->status) {
                                'hidden' => 'hide',
                                'removed' => 'remove',
                                'published' => 'approve',
                                default => 'flag',
                            },
                            auth()->id(),
                            $data['admin_reason'] ?? null,
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hide')->label('批次隱藏')->color('warning')
                        ->action(function (Collection $records): void {
                            foreach ($records as $r) {
                                $r->update(['status' => 'hidden']);
                                ModerationActionLogger::log('post', (int) $r->id, 'hide', auth()->id(), 'bulk hide');
                            }
                        }),
                    BulkAction::make('remove')->label('批次移除')->color('danger')->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $r) {
                                $r->update(['status' => 'removed']);
                                ModerationActionLogger::log('post', (int) $r->id, 'remove', auth()->id(), 'bulk remove');
                            }
                        }),
                ]),
            ]);
    }
}
