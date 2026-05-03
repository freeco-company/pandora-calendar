<?php

namespace App\Filament\Resources\CommunityPosts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CommunityPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('id')->disabled(),
            TextInput::make('user_id')
                ->label('原作者 user_id（admin 視野）')
                ->disabled()
                ->helperText('社群匿名 — 一般用戶看不到，僅 moderator 可見'),
            TextInput::make('anonymous_handle')->disabled(),
            TextInput::make('category')->disabled(),
            TextInput::make('title')->disabled()->columnSpanFull(),
            Textarea::make('body')->disabled()->rows(8)->columnSpanFull(),
            Select::make('status')
                ->label('狀態')
                ->options([
                    'pending' => '待審',
                    'published' => '已發佈',
                    'hidden' => '已隱藏',
                    'removed' => '已移除',
                ])
                ->required(),
            TextInput::make('moderation_score')->disabled()->numeric(),
            TextInput::make('reported_count')->disabled()->numeric(),
            TextInput::make('like_count')->disabled()->numeric(),
            TextInput::make('reply_count')->disabled()->numeric(),
            TextInput::make('published_at')->disabled(),
            Textarea::make('admin_reason')
                ->label('調整原因（會寫入 moderation log）')
                ->dehydrated(false)
                ->rows(3)
                ->columnSpanFull()
                ->helperText('例：違反社群準則 / 包含商品宣稱 / 用戶申訴後復原'),
        ]);
    }
}
