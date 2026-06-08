<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditAdminProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\GlobalSearch\IspGlobalSearchProvider;
use App\Support\CompanyBranding;
use App\Http\Middleware\EnsureStaffTwoFactorVerified;
use App\Http\Middleware\PreventAdminPageCache;
use App\Http\Middleware\RedirectSubscribersOnlinePreset;
use App\Http\Middleware\SetAppLocale;
use App\Support\AdminCommandPalette;
use App\Support\AdminRouteAssets;
use App\Support\Rbac\StaffCapability;
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
            ->homeUrl(function (): string {
                $user = auth()->user();

                if ($user !== null) {
                    $preferred = StaffCapability::for($user)->preferredHomeUrl();

                    if ($preferred !== null) {
                        return $preferred;
                    }
                }

                return Dashboard::getUrl();
            })
            ->login(false)
            ->profile(EditAdminProfile::class, isSimple: false)
            ->brandName(fn (): string => CompanyBranding::name())
            ->brandLogo(fn (): ?string => CompanyBranding::logoUrl())
            ->brandLogoHeight('1.95rem')
            ->favicon(fn (): ?string => CompanyBranding::faviconUrl())
            ->databaseNotificationsPolling('120s')
            ->colors([
                'primary' => Color::Violet,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Cyan,
            ])
            ->font('Outfit, ui-sans-serif, system-ui, sans-serif')
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
                // No group icons: Filament strips item icons and draws a second tree rail (grouped-border),
                // which duplicated our custom sidebar dots in admin-saas.css.
                NavigationGroup::make('Overview')->collapsed(true),
                NavigationGroup::make('ISP OS')->collapsed(true),
                NavigationGroup::make('Clients')->collapsed(true),
                NavigationGroup::make('Billing')->collapsed(true),
                NavigationGroup::make('Payments')->collapsed(true),
                NavigationGroup::make('Inventory Pro')->collapsed(true),
                NavigationGroup::make('Network')->collapsed(true),
                NavigationGroup::make('SMS Service')->collapsed(true),
                NavigationGroup::make('Support')->collapsed(true),
                NavigationGroup::make('Reports')->collapsed(true),
                NavigationGroup::make('BW Client')->collapsed(true),
                NavigationGroup::make('HR Management')->collapsed(true),
                NavigationGroup::make('Resellers')->collapsed(true),
                NavigationGroup::make('Accounts')->collapsed(true),
                NavigationGroup::make('Settings')->collapsed(true),
                NavigationGroup::make('System')->collapsed(true),
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
                RedirectSubscribersOnlinePreset::class,
                PreventAdminPageCache::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $html = view('filament.hooks.design-system')->render();

                    if (request()->routeIs('filament.admin.auth.*')) {
                        $html .= view('filament.hooks.auth-head')->render();
                    } else {
                        $html .= AdminRouteAssets::headLinks();
                    }

                    return $html;
                },
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                function (): string {
                    $html = view('filament.flash-banners')->render();

                    if (! request()->routeIs('filament.admin.auth.*')) {
                        $html .= view('filament.hooks.livewire-419-recovery')->render();
                    }

                    return $html;
                },
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
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
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
                function (): string {
                    if (request()->routeIs('filament.admin.auth.*')) {
                        return '';
                    }

                    $assetSalt = (int) config('isp.assets.version_salt', 0);
                    $jsV = static fn (string $file): int => (int) ((@filemtime(public_path($file)) ?: 1) + ($assetSalt * 1_000_000));

                    $html = AdminRouteAssets::spaStyleLoaderScript()
                        .view('filament.hooks.stale-topbar-cleanup')->render()
                        .view('filament.hooks.global-search-livewire-sync')->render()
                        .view('filament.hooks.command-palette', [
                        'commandItems' => AdminCommandPalette::items(),
                    ])->render()
                        .'<script src="'.asset('js/isp-admin-resilience.js').'?v='.$jsV('js/isp-admin-resilience.js').'" data-cfasync="false"></script>'
                        .'<script src="'.asset('js/admin-topbar-fix.js').'?v='.$jsV('js/admin-topbar-fix.js').'" data-cfasync="false"></script>'
                        .'<script src="'.asset('js/admin-sidebar-layout.js').'?v='.$jsV('js/admin-sidebar-layout.js').'" data-cfasync="false"></script>'
                        .'<script src="'.asset('js/mobile-sidebar-fix.js').'?v='.$jsV('js/mobile-sidebar-fix.js').'" data-cfasync="false"></script>'
                        .'<script src="'.asset('js/mobile-dock-pin.js').'?v='.$jsV('js/mobile-dock-pin.js').'" data-cfasync="false" data-navigate-once></script>'
                        .view('filament.hooks.mobile-dock')->render();

                    $user = auth()->user();
                    if ($user !== null && \App\Support\WebSipFeature::showsLiveCallUi($user)) {
                        $html .= view('filament.call-center.websip-widget')->render();
                    }

                    return $html;
                },
            );
    }
}
