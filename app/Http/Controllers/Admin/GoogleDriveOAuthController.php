<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\ManagePlatformBackups;
use App\Http\Controllers\Controller;
use App\Services\System\GoogleDriveBackupService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleDriveOAuthController extends Controller
{
    public function connect(GoogleDriveBackupService $googleDrive): RedirectResponse
    {
        abort_unless(ManagePlatformBackups::canAccess(), 403);

        return redirect()->away($googleDrive->buildAuthUrl());
    }

    public function callback(Request $request, GoogleDriveBackupService $googleDrive): RedirectResponse
    {
        abort_unless(ManagePlatformBackups::canAccess(), 403);

        $back = ManagePlatformBackups::getUrl();

        if ($request->filled('error')) {
            Notification::make()
                ->title('Google Drive connection cancelled')
                ->body((string) $request->query('error_description', $request->query('error')))
                ->warning()
                ->send();

            return redirect($back.'?tab=google');
        }

        $state = (string) $request->query('state', '');
        $expected = (string) session('google_drive_oauth_state', '');
        session()->forget('google_drive_oauth_state');

        if ($expected === '' || ! hash_equals($expected, $state)) {
            Notification::make()
                ->title('Google Drive connection failed')
                ->body('Invalid OAuth state. Try again.')
                ->danger()
                ->send();

            return redirect($back.'?tab=google');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            Notification::make()
                ->title('Google Drive connection failed')
                ->body('No authorization code received.')
                ->danger()
                ->send();

            return redirect($back.'?tab=google');
        }

        try {
            $result = $googleDrive->exchangeAuthorizationCode($code);
            $email = $result['email'] ?? 'connected';
            Notification::make()
                ->title('Google Drive connected')
                ->body("Account: {$email}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Google Drive connection failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return redirect($back.'?tab=google');
    }
}
