<?php

namespace App\Services\System;

use App\Exceptions\PlatformBackupException;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GoogleDriveBackupService
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public const SETTING_CLIENT_ID = 'backup.google_drive.client_id';

    public const SETTING_CLIENT_SECRET = 'backup.google_drive.client_secret';

    public const SETTING_REFRESH_TOKEN = 'backup.google_drive.refresh_token';

    public const SETTING_FOLDER_ID = 'backup.google_drive.folder_id';

    public const SETTING_ENABLED = 'backup.google_drive.enabled';

    public const SETTING_ACCOUNT_EMAIL = 'backup.google_drive.account_email';

    private const TOKEN_CACHE_KEY = 'backup.google_drive.access_token';

    public function isConfigured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    public function isConnected(): bool
    {
        return filled($this->refreshToken());
    }

    public function isEnabled(): bool
    {
        return $this->isConnected() && in_array(strtolower((string) AppSetting::getStoredValue(self::SETTING_ENABLED)), ['1', 'true', 'yes', 'on'], true);
    }

    public function redirectUri(): string
    {
        return route('admin.google-drive.callback');
    }

    public function buildAuthUrl(): string
    {
        if (! $this->isConfigured()) {
            throw new PlatformBackupException('Save Google Client ID and Client Secret first.');
        }

        $state = Str::random(40);
        session(['google_drive_oauth_state' => $state]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * @return array{email: ?string}
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new PlatformBackupException('Google token exchange failed: '.$response->body());
        }

        $refresh = $response->json('refresh_token');
        if (! filled($refresh)) {
            throw new PlatformBackupException('Google did not return a refresh token. Disconnect the app in your Google Account and connect again with consent.');
        }

        AppSetting::putValue(self::SETTING_REFRESH_TOKEN, $refresh);
        AppSetting::putValue(self::SETTING_ENABLED, '1');

        $access = (string) $response->json('access_token');
        Cache::put(self::TOKEN_CACHE_KEY, $access, now()->addMinutes(50));

        $email = $this->fetchAccountEmail($access);
        if ($email !== null) {
            AppSetting::putValue(self::SETTING_ACCOUNT_EMAIL, $email);
        }

        return ['email' => $email];
    }

    public function disconnect(): void
    {
        AppSetting::putValue(self::SETTING_REFRESH_TOKEN, null);
        AppSetting::putValue(self::SETTING_ACCOUNT_EMAIL, null);
        AppSetting::putValue(self::SETTING_ENABLED, '0');
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * @return array{status: string, message: string, file_id: ?string, file_name: ?string}
     */
    public function uploadBackupZip(string $zipPath): array
    {
        if (! $this->isEnabled()) {
            return [
                'status' => 'skipped',
                'message' => 'Google Drive upload is disabled or not connected.',
                'file_id' => null,
                'file_name' => null,
            ];
        }

        if (! is_file($zipPath)) {
            throw new PlatformBackupException('Backup ZIP missing for Google Drive upload.');
        }

        try {
            $folderId = $this->resolveFolderId();
            $fileName = basename($zipPath);
            $fileId = $this->uploadFileResumable($zipPath, $fileName, $folderId);
            $this->rotateRemoteBackups($folderId);

            return [
                'status' => 'ok',
                'message' => 'Uploaded to Google Drive.',
                'file_id' => $fileId,
                'file_name' => $fileName,
            ];
        } catch (\Throwable $e) {
            $message = $e instanceof PlatformBackupException ? $e->getMessage() : $e->getMessage();

            return [
                'status' => 'failed',
                'message' => $message,
                'file_id' => null,
                'file_name' => null,
            ];
        }
    }

    /**
     * @return array{configured: bool, connected: bool, enabled: bool, email: ?string, folder_id: ?string, redirect_uri: string}
     */
    public function status(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'connected' => $this->isConnected(),
            'enabled' => $this->isEnabled(),
            'email' => AppSetting::getStoredValue(self::SETTING_ACCOUNT_EMAIL),
            'folder_id' => $this->folderId(),
            'redirect_uri' => $this->redirectUri(),
        ];
    }

    private function uploadFileResumable(string $zipPath, string $fileName, string $folderId): string
    {
        $token = $this->accessToken();
        $size = (int) filesize($zipPath);

        $init = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => 'application/zip',
                'X-Upload-Content-Length' => (string) $size,
            ])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
                'name' => $fileName,
                'parents' => [$folderId],
            ]);

        if (! $init->successful()) {
            throw new PlatformBackupException('Google Drive upload init failed: '.$init->body());
        }

        $uploadUrl = $init->header('Location');
        if (! filled($uploadUrl)) {
            throw new PlatformBackupException('Google Drive did not return an upload URL.');
        }

        $stream = fopen($zipPath, 'rb');
        if ($stream === false) {
            throw new PlatformBackupException('Could not read backup ZIP for upload.');
        }

        try {
            $upload = Http::timeout(3600)
                ->withHeaders([
                    'Content-Length' => (string) $size,
                    'Content-Type' => 'application/zip',
                ])
                ->send('PUT', $uploadUrl, ['body' => $stream]);
        } finally {
            fclose($stream);
        }

        if (! $upload->successful()) {
            throw new PlatformBackupException('Google Drive upload failed: '.$upload->body());
        }

        $fileId = $upload->json('id');
        if (! filled($fileId)) {
            throw new PlatformBackupException('Google Drive upload succeeded but no file id returned.');
        }

        return (string) $fileId;
    }

    private function resolveFolderId(): string
    {
        $existing = $this->folderId();
        if (filled($existing)) {
            return $existing;
        }

        $token = $this->accessToken();
        $folderName = (string) config('backup.google_drive.folder_name', 'ISP Platform Backups');

        $search = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => sprintf(
                "mimeType='application/vnd.google-apps.folder' and name='%s' and trashed=false",
                str_replace("'", "\\'", $folderName),
            ),
            'fields' => 'files(id,name)',
            'pageSize' => 1,
        ]);

        if ($search->successful()) {
            $found = $search->json('files.0.id');
            if (filled($found)) {
                AppSetting::putValue(self::SETTING_FOLDER_ID, (string) $found);

                return (string) $found;
            }
        }

        $create = Http::withToken($token)->post('https://www.googleapis.com/drive/v3/files', [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if (! $create->successful()) {
            throw new PlatformBackupException('Could not create Google Drive folder: '.$create->body());
        }

        $id = (string) $create->json('id');
        AppSetting::putValue(self::SETTING_FOLDER_ID, $id);

        return $id;
    }

    private function rotateRemoteBackups(string $folderId): void
    {
        $token = $this->accessToken();
        $list = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "'{$folderId}' in parents and name contains 'isp-backup-' and trashed=false",
            'fields' => 'files(id,name,modifiedTime)',
            'orderBy' => 'modifiedTime desc',
            'pageSize' => 100,
        ]);

        if (! $list->successful()) {
            return;
        }

        $files = $list->json('files', []);
        $max = (int) config('backup.max_archives', 20);
        $cutoff = now()->subDays((int) config('backup.retention_days', 14));

        foreach ($files as $index => $file) {
            $modified = isset($file['modifiedTime']) ? strtotime((string) $file['modifiedTime']) : false;
            $tooOld = $modified !== false && $modified < $cutoff->timestamp;
            $overLimit = $index >= $max;

            if ($tooOld || $overLimit) {
                Http::withToken($token)->delete('https://www.googleapis.com/drive/v3/files/'.($file['id'] ?? ''));
            }
        }
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (filled($cached)) {
            return (string) $cached;
        }

        $refresh = $this->refreshToken();
        if (! filled($refresh)) {
            throw new PlatformBackupException('Google Drive is not connected.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new PlatformBackupException('Google token refresh failed. Re-connect Google Drive.');
        }

        $access = (string) $response->json('access_token');
        Cache::put(self::TOKEN_CACHE_KEY, $access, now()->addMinutes(50));

        return $access;
    }

    private function fetchAccountEmail(string $accessToken): ?string
    {
        $userinfo = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');
        if (! $userinfo->successful()) {
            return null;
        }

        return $userinfo->json('email');
    }

    private function clientId(): ?string
    {
        return AppSetting::getStoredValue(self::SETTING_CLIENT_ID)
            ?: config('backup.google_drive.client_id');
    }

    private function clientSecret(): ?string
    {
        return AppSetting::getStoredValue(self::SETTING_CLIENT_SECRET)
            ?: config('backup.google_drive.client_secret');
    }

    private function refreshToken(): ?string
    {
        return AppSetting::getStoredValue(self::SETTING_REFRESH_TOKEN);
    }

    private function folderId(): ?string
    {
        return AppSetting::getStoredValue(self::SETTING_FOLDER_ID);
    }
}
