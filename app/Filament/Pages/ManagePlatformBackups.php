<?php

namespace App\Filament\Pages;

use App\Exceptions\PlatformBackupException;
use App\Models\AutomaticProcess;
use App\Models\BackupDrive;
use App\Services\Automation\AutomaticProcessScheduler;
use App\Services\Automation\SchedulerStatus;
use App\Models\AppSetting;
use App\Services\System\BackupDriveMirrorService;
use App\Services\System\GoogleDriveBackupService;
use App\Services\System\PlatformBackupService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

class ManagePlatformBackups extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static string $view = 'filament.pages.manage-platform-backups';

    protected static ?string $navigationLabel = 'Backup & restore';

    protected static ?string $title = 'Backup download & restore';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 50;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $restoreData = [];

    /** @var array<string, mixed>|null */
    public ?array $driveData = [];

    public bool $showDriveForm = false;

    public ?int $editingDriveId = null;

    /** @var array<string, mixed>|null */
    public ?array $googleDriveData = [];

    /** @var array{configured: bool, connected: bool, enabled: bool, email: ?string, folder_id: ?string, redirect_uri: string} */
    public array $googleDriveSnapshot = [];

    public string $activeBackupTab = 'overview';

    public function mount(): void
    {
        $tab = (string) request()->query('tab', 'overview');
        if (in_array($tab, ['overview', 'google', 'drives'], true)) {
            $this->activeBackupTab = $tab;
        }

        $this->restoreForm->fill();
        $this->resetDriveForm();
        $this->refreshGoogleDriveSnapshot();
        $this->googleDriveForm->fill([
            'client_id' => AppSetting::getStoredValue(GoogleDriveBackupService::SETTING_CLIENT_ID) ?? '',
            'client_secret' => '',
            'folder_id' => AppSetting::getStoredValue(GoogleDriveBackupService::SETTING_FOLDER_ID) ?? '',
            'upload_enabled' => in_array(strtolower((string) AppSetting::getStoredValue(GoogleDriveBackupService::SETTING_ENABLED)), ['1', 'true', 'yes', 'on'], true),
        ]);
    }

    public function setBackupTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'google', 'drives'], true)) {
            $this->activeBackupTab = $tab;
        }
    }

    public function refreshGoogleDriveSnapshot(): void
    {
        try {
            $this->googleDriveSnapshot = app(GoogleDriveBackupService::class)->status();
        } catch (\Throwable) {
            $this->googleDriveSnapshot = [
                'configured' => false,
                'connected' => false,
                'enabled' => false,
                'email' => null,
                'folder_id' => null,
                'redirect_uri' => route('admin.google-drive.callback'),
            ];
        }
    }

    public function refreshBackupDrives(): void
    {
        unset($this->backupDrivesList);
    }

    protected function getForms(): array
    {
        return [
            'restoreForm',
            'driveForm',
            'googleDriveForm',
        ];
    }

    public function googleDriveForm(Form $form): Form
    {
        $status = app(GoogleDriveBackupService::class)->status();

        return $form
            ->schema([
                Section::make('Google Drive cloud backup')
                    ->description('প্রতিটি backup ZIP Google Drive-এ upload হবে। Google Cloud Console থেকে OAuth Client ID/Secret নিন (নিচে ধাপ দেখুন)।')
                    ->schema([
                        Placeholder::make('google_status')
                            ->label('Connection')
                            ->content(function () use ($status): string {
                                if ($status['connected']) {
                                    $email = $status['email'] ?? 'connected';

                                    return "Connected as {$email}";
                                }
                                if ($status['configured']) {
                                    return 'Credentials saved — click Connect Google Drive below.';
                                }

                                return 'Not configured';
                            }),
                        Placeholder::make('redirect_uri')
                            ->label('OAuth redirect URI (copy to Google Console)')
                            ->content($status['redirect_uri']),
                        TextInput::make('client_id')
                            ->label('Google Client ID')
                            ->maxLength(500),
                        TextInput::make('client_secret')
                            ->label('Google Client Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Leave blank when saving to keep the existing secret.'),
                        TextInput::make('folder_id')
                            ->label('Drive folder ID (optional)')
                            ->helperText('Empty = auto-create folder "'.config('backup.google_drive.folder_name').'"'),
                        Toggle::make('upload_enabled')
                            ->label('Upload each backup ZIP to Google Drive')
                            ->default(true),
                    ]),
            ])
            ->statePath('googleDriveData');
    }

    public function saveGoogleDriveSettings(): void
    {
        $state = $this->googleDriveForm->getState();

        if (filled($state['client_id'] ?? null)) {
            AppSetting::putValue(GoogleDriveBackupService::SETTING_CLIENT_ID, (string) $state['client_id']);
        }

        if (filled($state['client_secret'] ?? null)) {
            AppSetting::putValue(GoogleDriveBackupService::SETTING_CLIENT_SECRET, (string) $state['client_secret']);
        }

        AppSetting::putValue(
            GoogleDriveBackupService::SETTING_FOLDER_ID,
            filled($state['folder_id'] ?? null) ? (string) $state['folder_id'] : null,
        );

        AppSetting::putValue(
            GoogleDriveBackupService::SETTING_ENABLED,
            ($state['upload_enabled'] ?? false) ? '1' : '0',
        );

        $this->refreshGoogleDriveSnapshot();

        Notification::make()->title('Google Drive settings saved')->success()->send();
    }

    public function disconnectGoogleDrive(): void
    {
        app(GoogleDriveBackupService::class)->disconnect();
        $this->refreshGoogleDriveSnapshot();
        Notification::make()->title('Google Drive disconnected')->success()->send();
    }

    public function testGoogleDriveUpload(): void
    {
        $archives = app(PlatformBackupService::class)->listArchives();
        $latest = $archives[0] ?? null;

        if ($latest === null || empty($latest['zip_path'])) {
            Notification::make()->title('No ZIP backup on server')->warning()->send();

            return;
        }

        $result = app(GoogleDriveBackupService::class)->uploadBackupZip($latest['zip_path']);

        if (($result['status'] ?? '') === 'ok') {
            Notification::make()
                ->title('Google Drive upload OK')
                ->body($result['file_name'] ?? '')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Google Drive upload failed')
            ->body($result['message'] ?? 'Unknown error')
            ->danger()
            ->send();
    }

    public function restoreForm(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('restore_warning')
                    ->label('Important')
                    ->content('Restore replaces the database and storage/app files from your backup. A safety backup is created automatically before restore. All users will be briefly locked out (maintenance mode).'),
                FileUpload::make('backup_zip')
                    ->label('Backup file (.zip)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->maxSize(1024 * 512)
                    ->disk('local')
                    ->directory('backup-uploads')
                    ->required(),
                TextInput::make('confirm_phrase')
                    ->label('Type RESTORE to confirm')
                    ->required()
                    ->rule('in:RESTORE'),
            ])
            ->statePath('restoreData');
    }

    public function driveForm(Form $form): Form
    {
        $allowedRoots = implode(', ', config('backup.allowed_drive_roots', []));

        return $form
            ->schema([
                Section::make('External backup drive')
                    ->description('Mount USB/NFS disk on the server first (e.g. /mnt/usb-backup), then add that path here. Each backup ZIP is copied automatically.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Drive name')
                            ->required()
                            ->maxLength(120)
                            ->placeholder('Office USB · NAS volume 1'),
                        TextInput::make('mount_path')
                            ->label('Mount path (absolute)')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('/mnt/usb-backup')
                            ->helperText($allowedRoots !== ''
                                ? 'Allowed roots: '.$allowedRoots.' · Files go to …/'.config('backup.drive_subdirectory', 'isp-platform').'/'
                                : 'Files go to …/'.config('backup.drive_subdirectory', 'isp-platform').'/'),
                        Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true),
                        Toggle::make('mirror_on_backup')
                            ->label('Copy backup ZIP here after each backup')
                            ->default(true),
                        TextInput::make('max_archives')
                            ->label('Max ZIP copies on this drive (optional)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500),
                        TextInput::make('retention_days')
                            ->label('Retention days on this drive (optional)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650),
                    ]),
            ])
            ->statePath('driveData');
    }

    public function resetDriveForm(): void
    {
        $this->editingDriveId = null;
        $this->showDriveForm = false;
        $this->driveForm->fill([
            'name' => '',
            'mount_path' => '',
            'enabled' => true,
            'mirror_on_backup' => true,
            'max_archives' => null,
            'retention_days' => null,
        ]);
    }

    public function openAddDrive(): void
    {
        $this->resetDriveForm();
        $this->showDriveForm = true;
    }

    public function openEditDrive(int $id): void
    {
        $drive = BackupDrive::query()->findOrFail($id);
        $this->editingDriveId = $drive->id;
        $this->showDriveForm = true;
        $this->driveForm->fill([
            'name' => $drive->name,
            'mount_path' => $drive->mount_path,
            'enabled' => $drive->enabled,
            'mirror_on_backup' => $drive->mirror_on_backup,
            'max_archives' => $drive->max_archives,
            'retention_days' => $drive->retention_days,
        ]);
    }

    public function saveDrive(): void
    {
        $state = $this->driveForm->getState();
        $mirror = app(BackupDriveMirrorService::class);
        $probe = $mirror->probePath((string) ($state['mount_path'] ?? ''));

        if (! $probe['ok']) {
            Notification::make()
                ->title('Invalid drive path')
                ->body($probe['message'])
                ->danger()
                ->send();

            return;
        }

        $attributes = [
            'name' => (string) $state['name'],
            'mount_path' => $probe['resolved_path'] ?? (string) $state['mount_path'],
            'enabled' => (bool) ($state['enabled'] ?? true),
            'mirror_on_backup' => (bool) ($state['mirror_on_backup'] ?? true),
            'max_archives' => filled($state['max_archives'] ?? null) ? (int) $state['max_archives'] : null,
            'retention_days' => filled($state['retention_days'] ?? null) ? (int) $state['retention_days'] : null,
        ];

        if ($this->editingDriveId !== null) {
            BackupDrive::query()->whereKey($this->editingDriveId)->update($attributes);
            Notification::make()->title('Backup drive updated')->success()->send();
        } else {
            BackupDrive::query()->create($attributes);
            Notification::make()->title('Backup drive added')->success()->send();
        }

        $this->resetDriveForm();
        $this->refreshBackupDrives();
    }

    public function deleteDrive(int $id): void
    {
        BackupDrive::query()->whereKey($id)->delete();
        Notification::make()->title('Backup drive removed')->success()->send();

        if ($this->editingDriveId === $id) {
            $this->resetDriveForm();
        }

        $this->refreshBackupDrives();
    }

    public function testDrivePath(): void
    {
        $path = (string) ($this->driveForm->getState()['mount_path'] ?? '');
        $probe = app(BackupDriveMirrorService::class)->probePath($path);

        if ($probe['ok']) {
            $free = isset($probe['free_bytes']) ? $this->formatBytes((int) $probe['free_bytes']) : '—';
            Notification::make()
                ->title('Drive path OK')
                ->body('Resolved: '.$probe['resolved_path'].' · Free: '.$free)
                ->success()
                ->send();

            return;
        }

        Notification::make()->title('Drive path failed')->body($probe['message'])->danger()->send();
    }

    public function mirrorLatestToDrive(int $id): void
    {
        $drive = BackupDrive::query()->findOrFail($id);
        $archives = app(PlatformBackupService::class)->listArchives();
        $latest = $archives[0] ?? null;

        if ($latest === null || empty($latest['zip_path'])) {
            Notification::make()->title('No ZIP backup on server')->warning()->send();

            return;
        }

        $result = app(BackupDriveMirrorService::class)->mirrorZipToDrive($drive, $latest['zip_path']);

        if ($result['status'] === BackupDrive::STATUS_OK) {
            Notification::make()->title('Copied to '.$drive->name)->body($result['target'] ?? '')->success()->send();

            return;
        }

        Notification::make()->title('Mirror failed')->body($result['message'])->danger()->send();

        $this->refreshBackupDrives();
    }

    public function mirrorLatestToAllDrives(): void
    {
        $archives = app(PlatformBackupService::class)->listArchives();
        $latest = $archives[0] ?? null;

        if ($latest === null || empty($latest['zip_path'])) {
            Notification::make()->title('No ZIP backup on server')->warning()->send();

            return;
        }

        $results = app(BackupDriveMirrorService::class)->mirrorZipToEnabledDrives($latest['zip_path']);
        $ok = collect($results)->where('status', BackupDrive::STATUS_OK)->count();
        $failed = count($results) - $ok;

        Notification::make()
            ->title('Mirror finished')
            ->body("{$ok} succeeded, {$failed} failed/skipped.")
            ->color($failed > 0 ? 'warning' : 'success')
            ->send();

        $this->refreshBackupDrives();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function backupDrivesList(): array
    {
        if (! Schema::hasTable('backup_drives')) {
            return [];
        }

        $mirror = app(BackupDriveMirrorService::class);

        return BackupDrive::query()
            ->orderBy('name')
            ->get()
            ->map(function (BackupDrive $drive) use ($mirror): array {
                $archives = $mirror->listMirroredArchives($drive);
                $probe = $mirror->probePath($drive->mount_path);

                return [
                    'id' => $drive->id,
                    'name' => $drive->name,
                    'mount_path' => $drive->mount_path,
                    'target_dir' => $probe['ok'] ? $mirror->driveBackupDirectory($drive) : null,
                    'enabled' => $drive->enabled,
                    'mirror_on_backup' => $drive->mirror_on_backup,
                    'last_mirrored_at' => $drive->last_mirrored_at?->format('d M Y H:i'),
                    'last_mirror_status' => $drive->last_mirror_status,
                    'last_mirror_error' => $drive->last_mirror_error,
                    'free_human' => isset($probe['free_bytes']) ? $this->formatBytes((int) $probe['free_bytes']) : null,
                    'archive_count' => count($archives),
                    'latest_on_drive' => $archives[0]['label'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id: string, label: string, created_at: string, size_human: string}>
     */
    public function getArchives(): array
    {
        return collect(app(PlatformBackupService::class)->listArchives())
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'label' => $row['label'],
                'created_at' => $row['created_at'],
                'size_human' => $this->formatBytes($row['size_bytes']),
            ])
            ->all();
    }

    /**
     * @return array{enabled: bool, name: string, execute_at: string, next_run_at: ?string, last_run_at: ?string, last_status: ?string, process_exists: bool}
     */
    public function getAutoBackupStatus(): array
    {
        $process = AutomaticProcess::query()->withoutGlobalScopes()->where('slug', 'platform-backup')->first();

        if ($process === null) {
            return [
                'process_exists' => false,
                'enabled' => false,
                'name' => 'Platform backup (database + files)',
                'execute_at' => '02:00',
                'next_run_at' => null,
                'last_run_at' => null,
                'last_status' => null,
            ];
        }

        return [
            'process_exists' => true,
            'enabled' => (bool) $process->enabled,
            'name' => $process->name,
            'execute_at' => $process->execute_at ?? '02:00',
            'next_run_at' => $process->next_run_at?->format('d M Y H:i'),
            'last_run_at' => $process->last_run_at?->format('d M Y H:i'),
            'last_status' => $process->last_status,
        ];
    }

    /**
     * @return array{healthy: bool, label: string, cron_line: string}
     */
    public function getSchedulerHealth(): array
    {
        $cron = app(SchedulerStatus::class)->cronHealth();
        $cronLine = '* * * * * cd '.base_path().' && php artisan schedule:run >> storage/logs/scheduler.log 2>&1';

        return [
            'healthy' => $cron['healthy'],
            'label' => $cron['label'],
            'cron_line' => $cronLine,
        ];
    }

    public function toggleAutoBackup(): void
    {
        $process = AutomaticProcess::query()->withoutGlobalScopes()->where('slug', 'platform-backup')->first();

        if ($process === null) {
            Notification::make()
                ->title('Automatic process missing')
                ->body('Run: php artisan db:seed --class=AutomaticProcessSeeder')
                ->warning()
                ->send();

            return;
        }

        $process->enabled = ! $process->enabled;
        if ($process->enabled) {
            $process->next_run_at = app(AutomaticProcessScheduler::class)->computeNextRunAt($process);
        }
        $process->save();

        Notification::make()
            ->title($process->enabled ? 'Daily auto backup enabled' : 'Daily auto backup disabled')
            ->body($process->enabled
                ? 'Runs daily at '.$process->execute_at.' (server cron must be active).'
                : 'Manual backups still work from this page.')
            ->success()
            ->send();
    }

    public function runBackupNow(): void
    {
        try {
            $result = app(PlatformBackupService::class)->create();
            $mirrored = collect($result['mirror_results'] ?? [])->where('status', BackupDrive::STATUS_OK)->count();
            $mirrorFailed = collect($result['mirror_results'] ?? [])->where('status', BackupDrive::STATUS_FAILED)->count();
            $body = 'Saved as isp-backup-'.$result['stamp'].'.zip — use Download below.';
            if ($mirrored > 0 || $mirrorFailed > 0) {
                $body .= " Mirrored to {$mirrored} drive(s).";
                if ($mirrorFailed > 0) {
                    $body .= " {$mirrorFailed} drive(s) failed — check External drives.";
                }
            }
            $gd = $result['google_drive'] ?? null;
            if (($gd['status'] ?? '') === 'ok') {
                $body .= ' Uploaded to Google Drive.';
            } elseif (($gd['status'] ?? '') === 'failed') {
                $body .= ' Google Drive upload failed.';
            }
            Notification::make()
                ->title('Backup created on server')
                ->body($body)
                ->success()
                ->send();
        } catch (PlatformBackupException $e) {
            Notification::make()->title('Backup failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function runBackupNowAndDownload(): void
    {
        try {
            $result = app(PlatformBackupService::class)->create();
            if ($result['zip'] === null) {
                Notification::make()->title('Backup created (folder only)')->success()->send();

                return;
            }
            $this->redirect(route('admin.backups.download', ['id' => $result['stamp']]));
        } catch (PlatformBackupException $e) {
            Notification::make()->title('Backup failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function restoreUploadedBackup(): void
    {
        $state = $this->restoreForm->getState();
        $files = $state['backup_zip'] ?? [];

        if (! is_array($files) || $files === []) {
            Notification::make()->title('Choose a backup ZIP first')->warning()->send();

            return;
        }

        $relative = (string) ($files[0] ?? '');
        $path = Storage::disk('local')->path($relative);

        try {
            $result = app(PlatformBackupService::class)->restoreFromZip($path);
            Storage::disk('local')->delete($relative);

            Notification::make()
                ->title('Restore completed')
                ->body('Safety backup: '.$result['pre_restore_stamp'].'. Restored from: '.$result['restored_from'].'.')
                ->success()
                ->duration(15000)
                ->send();

            $this->restoreForm->fill();
        } catch (PlatformBackupException $e) {
            Notification::make()->title('Restore failed')->body($e->getMessage())->danger()->duration(20000)->send();
        }
    }

    public function deleteBackup(string $id): void
    {
        try {
            app(PlatformBackupService::class)->deleteArchive($id);
            Notification::make()->title('Backup deleted')->success()->send();
        } catch (PlatformBackupException $e) {
            Notification::make()->title('Delete failed')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label('Create & download backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Creates PostgreSQL dump + storage/app archive as ZIP. May take 1–2 minutes.')
                ->action(fn () => $this->runBackupNowAndDownload()),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasRole(['super-admin', 'isp-admin', 'admin'])
            || $user->can('system.backups');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
