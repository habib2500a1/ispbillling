<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikPppImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportClientsCsvPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static string $view = 'filament.pages.import-clients-csv';

    protected static ?string $navigationLabel = 'Import client CSV';

    protected static ?string $title = 'Import client CSV';

    protected static ?string $slug = 'import-clients-csv';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canCustomers();
    }

    public function mount(): void
    {
        $this->form->fill([
            'code_format' => config('subscriber.code_format', 'prefixed_monthly'),
            'create_missing' => true,
            'update_existing' => true,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('help')
                    ->label('')
                    ->content('Upload Excel or CSV with columns: username, password, profile, name, phone, customer_code. Download the sample template first.'),
                Select::make('mikrotik_server_id')
                    ->label('Router (MikroTik)')
                    ->options(fn (): array => MikrotikServer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->helperText('All imported rows link to this router for PPP sync.'),
                FileUpload::make('file')
                    ->label('Excel or CSV file')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->required()
                    ->storeFiles(false),
                Select::make('code_format')
                    ->label('New subscriber ID format')
                    ->options([
                        'prefixed_monthly' => 'CUST-yymm-#### (default)',
                        'numeric' => 'Numbers only',
                        'prefix_sequential' => 'Prefix + sequence',
                        'secret_as_code' => 'PPP username = subscriber code',
                    ])
                    ->required(),
                Toggle::make('create_missing')->label('Create new subscribers')->default(true),
                Toggle::make('update_existing')->label('Update existing')->default(true),
            ])
            ->statePath('data');
    }

    public function submitImport(): void
    {
        $data = $this->form->getState();
        $server = MikrotikServer::query()->find($data['mikrotik_server_id'] ?? null);
        if (! $server instanceof MikrotikServer) {
            Notification::make()->title('Select a router')->danger()->send();

            return;
        }

        $upload = $data['file'] ?? null;
        if (is_array($upload)) {
            $upload = reset($upload) ?: null;
        }

        if ($upload instanceof TemporaryUploadedFile) {
            $file = new UploadedFile(
                $upload->getRealPath(),
                $upload->getClientOriginalName(),
                $upload->getMimeType(),
                null,
                true,
            );
        } elseif ($upload instanceof UploadedFile) {
            $file = $upload;
        } else {
            Notification::make()->title('No file uploaded')->danger()->send();

            return;
        }

        $result = app(MikrotikPppImportService::class)->importFromFile($server, $file, [
            'create_missing' => (bool) ($data['create_missing'] ?? true),
            'update_existing' => (bool) ($data['update_existing'] ?? true),
            'code_format' => $data['code_format'] ?? null,
        ]);

        $body = sprintf(
            'Created: %d · Updated: %d · Skipped: %d',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        );
        if ($result['errors'] !== []) {
            $body .= ' · '.implode(' | ', array_slice($result['errors'], 0, 3));
        }

        $notification = Notification::make()->title('Import finished')->body($body);
        $result['errors'] === [] ? $notification->success() : $notification->warning();
        $notification->send();

        $this->form->fill([
            'mikrotik_server_id' => $server->id,
            'code_format' => $data['code_format'] ?? config('subscriber.code_format', 'prefixed_monthly'),
            'create_missing' => true,
            'update_existing' => true,
            'file' => null,
        ]);
    }

    public function downloadSample(): StreamedResponse
    {
        $service = app(MikrotikPppImportService::class);

        return response()->streamDownload(
            static function () use ($service): void {
                echo $service->sampleSpreadsheetBinary();
            },
            $service->sampleSpreadsheetFilename(),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
