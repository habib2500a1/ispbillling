<?php

namespace App\Filament\Pages;

use App\Models\MikrotikServer;
use App\Support\Rbac\StaffCapability;
use App\Services\Mikrotik\MikrotikPppImportService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportFromMikrotikPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string $view = 'filament.pages.import-from-mikrotik';

    protected static ?string $navigationLabel = 'Import from MikroTik';

    protected static ?string $title = 'Import from MikroTik';

    protected static ?string $slug = 'import-from-mikrotik';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canMikrotik();
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
                    ->content('Load PPP secrets from the router, select users to import as billing subscribers, then confirm.'),
                Select::make('mikrotik_server_id')
                    ->label('Router')
                    ->options(fn (): array => MikrotikServer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->data['selected'] = []),
                Placeholder::make('secret_count')
                    ->label('On router')
                    ->content(fn (): string => $this->secretCountLabel()),
                CheckboxList::make('selected')
                    ->label('PPP users to import')
                    ->options(fn (): array => $this->secretOptions())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->visible(fn (): bool => filled($this->data['mikrotik_server_id'] ?? null)),
                Select::make('code_format')
                    ->label('New subscriber ID format')
                    ->options([
                        'prefixed_monthly' => 'CUST-yymm-####',
                        'numeric' => 'Numbers only',
                        'prefix_sequential' => 'Prefix + sequence',
                        'secret_as_code' => 'PPP username = subscriber code',
                    ])
                    ->required(),
                Toggle::make('create_missing')->label('Create new')->default(true),
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

        $selected = $data['selected'] ?? [];
        if ($selected === []) {
            Notification::make()->title('Select at least one user')->warning()->send();

            return;
        }

        $result = app(MikrotikPppImportService::class)->importSelectedFromRouter($server, $selected, [
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

        Notification::make()
            ->title('Import finished')
            ->body($body)
            ->success()
            ->send();
    }

    private function secretCountLabel(): string
    {
        $count = count($this->secretOptions());

        return $count > 0 ? "{$count} PPP secret(s) found" : 'Select a router to load secrets';
    }

    /**
     * @return array<string, string>
     */
    private function secretOptions(): array
    {
        $serverId = $this->data['mikrotik_server_id'] ?? null;
        if (! filled($serverId)) {
            return [];
        }

        $server = MikrotikServer::query()->find($serverId);
        if (! $server instanceof MikrotikServer) {
            return [];
        }

        try {
            $secrets = app(MikrotikPppImportService::class)->listSecretsFromRouter($server);
        } catch (\Throwable) {
            return [];
        }

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

        return $options;
    }
}
