<?php

namespace App\Filament\Pages;

use App\Exceptions\PlatformBackupException;
use App\Models\AutomaticProcess;
use App\Services\Automation\AutomaticProcessScheduler;
use App\Services\Automation\SchedulerStatus;
use App\Services\System\PlatformBackupService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ManagePlatformBackups extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static string $view = 'filament.pages.manage-platform-backups';

    protected static ?string $navigationLabel = 'Backup & restore';

    protected static ?string $title = 'Backup download & restore';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 50;

    /** @var array<string, mixed>|null */
    public ?array $restoreData = [];

    public function mount(): void
    {
        $this->restoreForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'restoreForm',
        ];
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
            Notification::make()
                ->title('Backup created on server')
                ->body('Saved as isp-backup-'.$result['stamp'].'.zip — use Download below.')
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
