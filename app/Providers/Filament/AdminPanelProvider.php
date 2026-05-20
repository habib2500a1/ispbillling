<?php

namespace App\Providers\Filament;

use App\Filament\Auth\AdminLogin;
use App\Filament\Auth\EditAdminProfile;
use App\Filament\GlobalSearch\IspGlobalSearchProvider;
use App\Support\CompanyBranding;
use App\Http\Middleware\EnsureStaffTwoFactorVerified;
use App\Http\Middleware\SetAppLocale;
use App\Support\AdminCommandPalette;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Enums\ThemeMode;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(AdminLogin::class)
            ->profile(EditAdminProfile::class, isSimple: false)
            ->brandName(fn (): string => CompanyBranding::name())
            ->brandLogo(fn (): ?string => CompanyBranding::logoUrl())
            ->brandLogoHeight('2.25rem')
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Teal,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('ui-sans-serif, system-ui, sans-serif')
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::System)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('20rem')
            ->collapsedSidebarWidth('4.5rem')
            ->globalSearch(IspGlobalSearchProvider::class)
            ->globalSearchDebounce('300ms')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->maxContentWidth('full')
            ->navigationGroups([
                NavigationGroup::make('Overview')->collapsed(false),
                NavigationGroup::make('Clients')->collapsed(false),
                NavigationGroup::make('Billing')->collapsed(false),
                NavigationGroup::make('Payments')->collapsed(false),
                NavigationGroup::make('OLT & Tools')->collapsed(false),
                NavigationGroup::make('Network')->collapsed(false),
                NavigationGroup::make('SMS Service')->collapsed(false),
                NavigationGroup::make('Support')->collapsed(false),
                NavigationGroup::make('Reports')->collapsed(false),
                NavigationGroup::make('BW Client')->collapsed(false),
                NavigationGroup::make('HRM')->collapsed(false),
                NavigationGroup::make('Inventory')->collapsed(false),
                NavigationGroup::make('Resellers')->collapsed(false),
                NavigationGroup::make('Accounts')->collapsed(false),
                NavigationGroup::make('Settings')->collapsed(false),
                NavigationGroup::make('System')->collapsed(false),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetAppLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureStaffTwoFactorVerified::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.hooks.design-system')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.flash-banners')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.hooks.topbar-mobile-logo')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => view('filament.hooks.sidebar-toolbar')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.hooks.sidebar-footer-collapse')->render(),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.hooks.topbar-extras')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.hooks.auth-login-flash')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('components.mobile-app-promo', ['variant' => 'compact'])->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.hooks.command-palette', [
                    'commandItems' => AdminCommandPalette::items(),
                ])->render()
                    .view('filament.hooks.mobile-dock')->render(),
            );
    }
}
