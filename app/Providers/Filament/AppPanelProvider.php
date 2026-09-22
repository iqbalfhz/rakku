<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Tenancy\EditBookProfile;
use App\Filament\App\Pages\Tenancy\RegisterBook;
use App\Models\Book;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->brandName('RakKu')
            ->login()
            ->registration()
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->tenant(Book::class)
            ->tenantRegistration(RegisterBook::class)
            ->tenantProfile(EditBookProfile::class)
            ->databaseNotifications()
            ->databaseTransactions()
            ->sidebarFullyCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): View => view('filament.app.sidebar-accordion'),
            )
            ->userMenuItems([
                Action::make('adminPanel')
                    ->label('Panel admin')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => filament()->getPanel('admin')->getUrl())
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->is_admin),
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
