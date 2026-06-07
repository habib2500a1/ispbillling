<?php

namespace App\Filament\Pages;

use App\Support\CompanyBranding;
use App\Support\MobileApkBuildInfo;
use App\Support\MobileAppLinks;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

final class ManageMobileApp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string $view = 'filament.pages.manage-mobile-app';

    protected static ?string $navigationLabel = 'Mobile app';

    protected static ?string $title = 'Mobile app & APK';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $slug = 'mobile-app';

    protected static ?int $navigationSort = 45;

    public static function canAccess(): bool
    {
        return ManageCompanySetup::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rebuildApks')
                ->label('Rebuild APK for this domain')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Rebuild mobile APKs')
                ->modalDescription('Builds APKs for the current APP_URL. Requires Flutter on the server, or syncs from GitHub if Flutter is missing.')
                ->action(function (): void {
                    Artisan::call('isp:rebuild-mobile-apks');
                    Notification::make()
                        ->title('Mobile APK rebuild started')
                        ->body('Check storage/logs/rebuild-mobile-apks.log. Download links update when complete.')
                        ->success()
                        ->send();
                }),
            Action::make('syncFromGithub')
                ->label('Sync from GitHub')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('isp:rebuild-mobile-apks', ['--sync' => true]);
                    Notification::make()
                        ->title('GitHub APK sync started')
                        ->warning()
                        ->body('Synced APKs may still point at an old domain until you rebuild with Flutter.')
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMobileStatus(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $build = MobileApkBuildInfo::read();
        $radiantPath = public_path('downloads/isp-radiant.apk');
        $mfsPath = public_path('downloads/isp-mfs-verify.apk');

        return [
            'app_url' => $appUrl,
            'api_base_url' => $appUrl.'/api/v1',
            'logo_url' => CompanyBranding::logoUrl(),
            'logo_runtime' => true,
            'launcher_rebuild' => true,
            'build_info' => $build,
            'build_status' => MobileApkBuildInfo::statusLabel(),
            'domain_matches' => MobileApkBuildInfo::domainMatchesAppUrl(),
            'radiant_download' => MobileAppLinks::downloadUrl(),
            'mfs_download' => MobileAppLinks::mfsVerifyDownloadUrl(),
            'radiant_exists' => is_file($radiantPath),
            'radiant_size_mb' => is_file($radiantPath) ? round(filesize($radiantPath) / 1024 / 1024, 2) : 0,
            'radiant_modified' => is_file($radiantPath) ? date('Y-m-d H:i:s', (int) filemtime($radiantPath)) : null,
            'mfs_exists' => is_file($mfsPath),
            'flutter_available' => $this->flutterAvailable(),
        ];
    }

    private function flutterAvailable(): bool
    {
        $paths = ['/opt/flutter/bin/flutter', '/usr/local/bin/flutter', 'flutter'];
        foreach ($paths as $path) {
            if ($path === 'flutter') {
                exec('command -v flutter 2>/dev/null', $out, $code);

                return $code === 0;
            }

            if (is_executable($path)) {
                return true;
            }
        }

        return false;
    }
}
