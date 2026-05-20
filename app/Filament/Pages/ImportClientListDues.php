<?php

namespace App\Filament\Pages;

use App\Services\Import\ClientListDueImporter;
use App\Support\BillingMetricsCache;
use App\Support\TenantResolver;
use App\Services\Import\IspDigitalCurrentBillingSyncService;
use App\Services\Import\IspDigitalSessionClient;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportClientListDues extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static string $view = 'filament.pages.import-client-list-dues';

    protected static ?string $navigationLabel = 'Import client dues';

    protected static ?string $title = 'Import Client List (PDF / Excel)';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'import-client-dues';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('client_list_file')
                    ->label('Client List file (PDF, CSV, XLSX)')
                    ->helperText('Upload the same file you export from ISP Digital (e.g. Client_List_21-5-2026.pdf).')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'text/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(51200)
                    ->directory('imports/uploads')
                    ->visibility('private')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function importFromFile(): void
    {
        $state = $this->form->getState();
        $uploaded = $state['client_list_file'] ?? null;
        $path = $this->resolveUploadedPath($uploaded);

        if ($path === null) {
            Notification::make()->title('No file')->body('Choose a PDF, CSV, or Excel file first.')->danger()->send();

            return;
        }

        try {
            $stats = app(ClientListDueImporter::class)->importFromPath($path);
            BillingMetricsCache::flush((int) TenantResolver::requiredTenantId());
            $this->notifyStats('Import complete', $stats);
            $this->form->fill([]);
        } catch (\Throwable $e) {
            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function syncFromIspDigital(): void
    {
        $password = (string) config('isp_digital.password');
        if ($password === '') {
            Notification::make()->title('ISP Digital not configured')->body('Set ISP_DIGITAL_PASSWORD in .env')->danger()->send();

            return;
        }

        try {
            $client = new IspDigitalSessionClient(
                (string) config('isp_digital.base_url'),
                (string) config('isp_digital.username'),
                $password,
            );
            $client->login();
            $result = app(IspDigitalCurrentBillingSyncService::class)->syncAll($client);
            $s = $result['summary'];
            BillingMetricsCache::flush((int) TenantResolver::requiredTenantId());

            Notification::make()
                ->title('Synced from ISP Digital')
                ->body(sprintf(
                    'Due: %s BDT · Collected: %s BDT · %d customers',
                    number_format($s['due'] ?? 0, 2),
                    number_format($s['collected_bill'] ?? 0, 2),
                    $result['customers'],
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * @param  mixed  $uploaded
     */
    private function resolveUploadedPath(mixed $uploaded): ?string
    {
        if ($uploaded instanceof TemporaryUploadedFile) {
            return $uploaded->getRealPath() ?: null;
        }

        if (is_array($uploaded)) {
            $first = reset($uploaded);

            return $this->resolveUploadedPath($first);
        }

        if (is_string($uploaded) && $uploaded !== '') {
            $full = storage_path('app/'.$uploaded);

            return is_readable($full) ? $full : null;
        }

        return null;
    }

    /**
     * @param  array{updated: int, skipped: int, not_found: int, zeroed: int, errors: list<string>}  $stats
     */
    private function notifyStats(string $title, array $stats): void
    {
        $body = sprintf(
            'Updated: %d · Not found: %d · Skipped: %d',
            $stats['updated'],
            $stats['not_found'],
            $stats['skipped'],
        );

        if ($stats['errors'] !== []) {
            $body .= "\n".implode("\n", array_slice($stats['errors'], 0, 5));
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super-admin', 'isp-admin', 'admin', 'isp-manager']);
    }
}
