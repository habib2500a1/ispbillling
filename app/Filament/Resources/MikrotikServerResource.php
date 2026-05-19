<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MikrotikServerResource\Pages;
use App\Models\MikrotikServer;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Services\Mikrotik\MikrotikPppImportService;
use App\Services\Mikrotik\MikrotikServerService;
use App\Support\TenantResolver;
use Filament\Forms;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class MikrotikServerResource extends Resource
{
    protected static ?string $model = MikrotikServer::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'MikroTik';

    protected static ?string $modelLabel = 'MikroTik server';

    protected static ?string $pluralModelLabel = 'MikroTik servers';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Unique per tenant (e.g. Core-1, BRAS-Dhaka).'),
                Forms\Components\TextInput::make('host')
                    ->label('Host / IP')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('api_port')
                    ->label('API port')
                    ->numeric()
                    ->default(8728)
                    ->minValue(1)
                    ->maxValue(65535)
                    ->required(),
                Forms\Components\Toggle::make('use_ssl')
                    ->label('API SSL (8729)')
                    ->helperText('Use when RouterOS API-SSL is enabled on port 8729 (set port accordingly).'),
                Forms\Components\Toggle::make('legacy_login')
                    ->label('Legacy login (RouterOS pre-6.43)')
                    ->helperText('Only if the router still uses pre-6.43 API login.'),
                Forms\Components\TextInput::make('api_username')
                    ->label('API user')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('api_password')
                    ->label('API password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank when editing to keep the current password.'),
                Forms\Components\TextInput::make('default_ppp_password')
                    ->label('Default PPPoE password (bulk)')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Used for sync when a customer has no individual MikroTik PPP password.'),
                Forms\Components\TextInput::make('ppp_profile_default')
                    ->label('Default PPP profile on router')
                    ->maxLength(64)
                    ->helperText('Optional RouterOS profile name applied on sync (e.g. default, residential).'),
                Forms\Components\Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true),
                Forms\Components\Textarea::make('meta_preview')
                    ->label('Stored API snapshot (read-only)')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(10)
                    ->columnSpanFull()
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->afterStateHydrated(function (Forms\Components\Textarea $component): void {
                        $r = $component->getRecord();
                        if ($r instanceof MikrotikServer && filled($r->meta)) {
                            $component->state(json_encode($r->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('host')
                    ->description(fn (MikrotikServer $r): string => ($r->use_ssl ? 'ssl://' : '').$r->host.':'.$r->api_port),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->boolean()
                    ->label('On'),
                Tables\Columns\TextColumn::make('subscribers_count')
                    ->label('Subscribers')
                    ->counts('customers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_api_status')
                    ->label('API')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_checked_at')
                    ->label('Last check')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(40)
                    ->tooltip(fn (MikrotikServer $r): ?string => $r->last_error)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
            ])
            ->actions([
                Tables\Actions\Action::make('check_status')
                    ->label('Check')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (MikrotikServer $record): void {
                        app(MikrotikServerService::class)->probeAndPersist($record->fresh());
                        Notification::make()
                            ->title('Status refreshed')
                            ->body('Current state: '.$record->fresh()->last_api_status)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('fetch_details')
                    ->label('Fetch details')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->modalHeading('Fetch RouterOS details?')
                    ->modalDescription('Reads identity, resource, routerboard, clock, and package list via API into this row’s meta (api_detail).')
                    ->action(function (MikrotikServer $record): void {
                        $detail = app(MikrotikServerService::class)->fetchRouterDetails($record->fresh());
                        $identity = is_array($detail['identity'] ?? null)
                            ? (string) ($detail['identity']['name'] ?? json_encode($detail['identity']))
                            : '—';
                        $body = isset($detail['error'])
                            ? 'Error: '.$detail['error']
                            : 'Identity: '.$identity;
                        Notification::make()
                            ->title('Router snapshot saved')
                            ->body($body)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reboot')
                    ->label('Reboot')
                    ->icon('heroicon-o-power')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reboot this router?')
                    ->modalDescription('Active sessions will drop. Only continue during a maintenance window.')
                    ->action(function (MikrotikServer $record): void {
                        app(MikrotikServerService::class)->reboot($record);
                        Notification::make()
                            ->title('Reboot sent')
                            ->body('If the API accepted the command, the router will restart shortly.')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('import_secrets')
                    ->label('Import selected users')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->modalHeading('Import PPP users from MikroTik')
                    ->modalDescription('Load the list, tick only the users you want, then import. Nothing is imported until you confirm.')
                    ->modalWidth('3xl')
                    ->form(function (MikrotikServer $record): array {
                        $secrets = app(MikrotikPppImportService::class)->listSecretsFromRouter($record);
                        $options = [];
                        foreach ($secrets as $secret) {
                            $label = $secret['name'];
                            if (! empty($secret['profile'])) {
                                $label .= ' · '.$secret['profile'];
                            }
                            if ($secret['disabled']) {
                                $label .= ' (disabled)';
                            }
                            $options[$secret['name']] = $label;
                        }

                        return [
                            Forms\Components\Placeholder::make('secret_count')
                                ->label('On router')
                                ->content(count($options).' PPP secret(s) found'),
                            Forms\Components\CheckboxList::make('selected')
                                ->label('Users to import')
                                ->options($options)
                                ->searchable()
                                ->bulkToggleable()
                                ->columns(2)
                                ->required()
                                ->helperText('Select only the logins you need. Use Excel upload for bulk with phone/name columns.'),
                            Forms\Components\Select::make('code_format')
                                ->label('New subscriber ID format')
                                ->options([
                                    'prefixed_monthly' => 'CUST-yymm-####',
                                    'numeric' => 'Numbers only',
                                    'prefix_sequential' => 'Prefix + sequence',
                                    'secret_as_code' => 'PPP username = subscriber code',
                                ])
                                ->default(config('subscriber.code_format', 'prefixed_monthly')),
                            Forms\Components\Toggle::make('create_missing')->default(true)->label('Create new'),
                            Forms\Components\Toggle::make('update_existing')->default(true)->label('Update existing'),
                        ];
                    })
                    ->action(function (MikrotikServer $record, array $data): void {
                        $selected = $data['selected'] ?? [];
                        if (! is_array($selected) || $selected === []) {
                            Notification::make()->title('Select at least one user')->warning()->send();

                            return;
                        }
                        $result = app(MikrotikPppImportService::class)->importSelectedFromRouter($record, $selected, [
                            'create_missing' => (bool) ($data['create_missing'] ?? true),
                            'update_existing' => (bool) ($data['update_existing'] ?? true),
                            'code_format' => $data['code_format'] ?? null,
                        ]);
                        $sync = ['sessions_open' => 0];
                        try {
                            TenantResolver::fake((int) $record->tenant_id);
                            $sync = app(BandwidthCollectionService::class)->collectForTenant((int) $record->tenant_id);
                        } catch (\Throwable) {
                            // Import succeeded; online sync can be retried from Bandwidth monitor.
                        }
                        $body = sprintf(
                            'Created: %d · Updated: %d · Skipped: %d · Online now: %d',
                            $result['created'],
                            $result['updated'],
                            $result['skipped'],
                            $sync['sessions_open'] ?? 0,
                        );
                        if ($result['errors'] !== []) {
                            $body .= ' · '.implode(' | ', array_slice($result['errors'], 0, 3));
                        }
                        Notification::make()->title('Import finished')->body($body)->success()->send();
                    }),
                Tables\Actions\Action::make('purge_mikrotik_imports')
                    ->label('Delete MikroTik imports')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete subscribers imported from this router?')
                    ->modalDescription('Removes only subscribers with import_source=mikrotik linked to this server. Manual subscribers are kept. Invoices/payments tied to them may be affected — use with care.')
                    ->action(function (MikrotikServer $record): void {
                        $result = app(MikrotikPppImportService::class)->purgeMikrotikImported(
                            (int) $record->tenant_id,
                            (int) $record->id,
                        );
                        Notification::make()
                            ->title('Deleted '.$result['deleted'].' imported subscriber(s)')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('import_excel')
                    ->label('Upload Excel/CSV')
                    ->icon('heroicon-o-document-arrow-up')
                    ->modalDescription('Columns: username, password, profile, name, phone, customer_code, disabled. Use “Download sample Excel” on the list page for a template.')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('File (.csv, .xlsx)')
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
                                'numeric' => 'Numbers only (10001, 10002…)',
                                'prefix_sequential' => 'Prefix + sequence (from .env)',
                                'secret_as_code' => 'Use PPP username as subscriber code',
                            ])
                            ->default(config('subscriber.code_format', 'prefixed_monthly')),
                        Forms\Components\Toggle::make('create_missing')->default(true)->label('Create new subscribers'),
                        Forms\Components\Toggle::make('update_existing')->default(true)->label('Update existing'),
                    ])
                    ->action(function (MikrotikServer $record, array $data): void {
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
                        $result = app(MikrotikPppImportService::class)->importFromFile($record, $file, [
                            'create_missing' => (bool) ($data['create_missing'] ?? true),
                            'update_existing' => (bool) ($data['update_existing'] ?? true),
                            'code_format' => $data['code_format'] ?? null,
                        ]);
                        $body = sprintf('Created: %d · Updated: %d · Skipped: %d', $result['created'], $result['updated'], $result['skipped']);
                        Notification::make()->title('File import finished')->body($body)->success()->send();
                    }),
                Tables\Actions\Action::make('sync_ppp')
                    ->label('Push PPP → router')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->requiresConfirmation()
                    ->modalHeading('Sync PPPoE secrets to this MikroTik?')
                    ->modalDescription('Creates or updates /ppp/secret entries for customers in this tenant. Each customer needs a MikroTik PPP password (customer record) or this server must have a default PPP password set.')
                    ->action(function (MikrotikServer $record): void {
                        $svc = app(MikrotikServerService::class);
                        $result = $svc->syncPppSecrets($record);
                        $errSample = array_slice($result['errors'], 0, 5);
                        $body = sprintf(
                            'Created: %d, updated: %d, skipped (no usable password): %d.',
                            $result['created'],
                            $result['updated'],
                            $result['skipped'],
                        );
                        if ($errSample !== []) {
                            $body .= ' Errors: '.implode(' | ', $errSample);
                        }
                        $notification = Notification::make()
                            ->title('PPP sync finished')
                            ->body($body);
                        if ($result['skipped'] > 0 || $result['errors'] !== []) {
                            $notification->warning();
                        } else {
                            $notification->success();
                        }
                        $notification->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('poll_all')
                    ->label('Refresh all status')
                    ->icon('heroicon-o-signal')
                    ->action(function (): void {
                        $svc = app(MikrotikServerService::class);
                        foreach (MikrotikServer::query()->cursor() as $server) {
                            $svc->probeAndPersist($server);
                        }
                        Notification::make()
                            ->title('All servers polled')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('bulk_poll')
                        ->label('Refresh status')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Collection $records): void {
                            $svc = app(MikrotikServerService::class);
                            foreach ($records as $server) {
                                $svc->probeAndPersist($server);
                            }
                            Notification::make()
                                ->title('Selected servers updated')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMikrotikServers::route('/'),
            'create' => Pages\CreateMikrotikServer::route('/create'),
            'edit' => Pages\EditMikrotikServer::route('/{record}/edit'),
        ];
    }
}
