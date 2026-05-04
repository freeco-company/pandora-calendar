<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * 潘朵拉月曆 Admin Panel.
 *
 * Mirrors pandora-meal AdminPanelProvider conventions for cross-product
 * consistency:
 *   - same color tokens (gold-brown primary aligned with mothership)
 *   - light-mode only
 *   - Chinese navigation groups
 *   - SPA + global search + sidebar collapsible
 *
 * Auth gate: User::canAccessPanel() rejects anyone without is_admin = true.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            ->brandName('潘朵拉月曆 Admin')
            ->login()

            // Aligned with mothership / pandora-meal gold-brown brand.
            ->colors([
                'primary' => Color::hex('#9F6B3E'),
                'danger' => Color::Red,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'gray' => Color::Stone,
            ])
            ->darkMode(false)

            ->navigationGroups([
                '核心數據',
                '社群審查',
                '用戶反饋',
                '訂閱',
                '使用者',
                '進階管理',
                '系統',
            ])

            ->font('Noto Sans TC')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->spa()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])

            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\ModerationQueueWidget::class,
                \App\Filament\Widgets\UsageOverviewWidget::class,
                \App\Filament\Widgets\LlmCostWidget::class,
                // wave 14 expanded monitoring — 對齊 wave 11-13 新功能
                \App\Filament\Widgets\LlmCostBreakdownWidget::class,
                \App\Filament\Widgets\PushSendBreakdownWidget::class,
                \App\Filament\Widgets\EconomyHealthWidget::class,
                \App\Filament\Widgets\UserHealthWidget::class,
                \App\Filament\Widgets\ContentEngagementWidget::class,
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                'throttle:60,1',
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
