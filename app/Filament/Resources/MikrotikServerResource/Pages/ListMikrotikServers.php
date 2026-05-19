<?php

namespace App\Filament\Resources\MikrotikServerResource\Pages;

use App\Filament\Resources\MikrotikServerResource;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikPppImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListMikrotikServers extends ListRecords
{
    protected static string $resource = MikrotikServerResource::class;

    protected static string $view = 'filament.resources.mikrotik-server-resource.pages.list-mikrotik-servers';

    /**
     * @return array{total: int, enabled: int, online: int, subscribers: int}
     */
    public function getRouterStats(): array
    {
        $base = MikrotikServer::query();

        return [
            'total' => (int) (clone $base)->count(),
            'enabled' => (int) (clone $base)->where('is_enabled', true)->count(),
            'online' => (int) (clone $base)->where('last_api_status', 'online')->count(),
            'subscribers' => (int) MikrotikServer::query()->withCount('customers')->get()->sum('customers_count'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_sample_excel')
                ->label('Download sample Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $service = app(MikrotikPppImportService::class);

                    return response()->streamDownload(
                        static function () use ($service): void {
                            echo $service->sampleSpreadsheetBinary();
                        },
                        $service->sampleSpreadsheetFilename(),
                        [
                            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                    );
                }),
            Actions\Action::make('upload_excel')
                ->label('Upload Excel/CSV')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->modalHeading('Import PPP subscribers from Excel')
                ->modalDescription('Upload a filled sample file. Choose which MikroTik router these subscribers belong to. Download the sample first if you need the column layout.')
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Select::make('mikrotik_server_id')
                        ->label('MikroTik server')
                        ->options(fn (): array => MikrotikServer::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
                        ->helperText('All rows in the file will be linked to this router.'),
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel or CSV file')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required()
                        ->storeFiles(false),
                    Forms\Components\Select::make('code_format')
                        ->label('New subscriber ID format')
                        ->options([
                            'prefixed_monthly' => 'CUST-yymm-#### (default)',
                            'numeric' => 'Numbers only',
                            'prefix_sequential' => 'Prefix + sequence',
                            'secret_as_code' => 'PPP username = subscriber code',
                        ])
                        ->default(config('subscriber.code_format', 'prefixed_monthly')),
                    Forms\Components\Toggle::make('create_missing')->default(true)->label('Create new subscribers'),
                    Forms\Components\Toggle::make('update_existing')->default(true)->label('Update existing'),
                ])
                ->action(function (array $data): void {
                    $server = MikrotikServer::query()->find($data['mikrotik_server_id'] ?? null);
                    if (! $server instanceof MikrotikServer) {
                        Notification::make()->title('Select a MikroTik server')->danger()->send();

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
                    if ($result['errors'] !== []) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }
                    $notification->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
