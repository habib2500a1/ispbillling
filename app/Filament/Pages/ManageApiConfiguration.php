<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Models\IntegrationSettingsAudit;
use App\Services\Integrations\TenantApiSettingsService;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * Sheba-Fi "API Configuration" — subdomain + HMAC for callbacks (secrets encrypted per tenant).
 *
 * @property Form $form
 */
class ManageApiConfiguration extends Page
{
    use HidesHubNavigation;
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.pages.manage-api-configuration';

    protected static ?string $navigationLabel = 'API configuration';

    protected static ?string $title = 'API configuration';

    protected static ?string $slug = 'api-configuration';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public ?string $revealedHmacSecret = null;

    public ?string $revealedSanctumToken = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasAnyRole(['super-admin', 'isp-admin']) ?? false);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $api = app(TenantApiSettingsService::class);

        $this->form->fill([
            'api_host' => $api->getRawApiHostOverride(),
            'hmac_masked' => $api->maskedWebhookHmacSecret() ?: '— not set —',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        $api = app(TenantApiSettingsService::class);
        $tenantId = TenantResolver::requiredTenantId();

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Tenant API host')
                            ->description('Sheba-Fi subdomain binding. Leave empty to use {tenant-slug}.' . config('isp.tenant_base_domain', 'your-base-domain') . '.')
                            ->schema([
                                TextInput::make('api_host')
                                    ->label('API / tenant host')
                                    ->placeholder('e.g. bill.flixbd.xyz')
                                    ->helperText('Resolved host: ' . $api->apiHost($tenantId))
                                    ->maxLength(255),
                                Placeholder::make('api_base_url')
                                    ->label('REST API base')
                                    ->content($api->apiBaseUrl($tenantId) . '/api/v1'),
                            ]),
                        Section::make('Webhook HMAC (callbacks)')
                            ->description('Safer than a plain secret in URL. Partners sign the raw JSON body. Also supports legacy X-ISP-Webhook-Secret from .env.')
                            ->schema([
                                Placeholder::make('hmac_masked')
                                    ->label('HMAC secret')
                                    ->content(fn (): string => $this->data['hmac_masked'] ?? '—'),
                                Placeholder::make('hmac_header_help')
                                    ->label('Signature header')
                                    ->content('X-ISP-Signature: t={unix},v1={hmac_sha256_hex} where HMAC key is the secret below and signed payload is "{unix}.{raw_json_body}".'),
                            ]),
                        Section::make('Webhook URLs (this server)')
                            ->schema([
                                Placeholder::make('webhooks')
                                    ->label('Endpoints')
                                    ->content(fn (): string => implode("\n", [
                                        'POST ' . url('/api/webhooks/call-center') . ' — Call center CDR',
                                        'POST ' . url('/api/webhooks/support-tickets') . ' — Support ingest',
                                        'Payment gateways — see Payments → Payment settings',
                                    ])),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate_hmac')
                ->label('Regenerate HMAC')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate HMAC secret?')
                ->modalDescription('Old signatures will stop working immediately. Copy the new secret once — it will not be shown again.')
                ->action(function (): void {
                    $result = app(TenantApiSettingsService::class)->regenerateWebhookHmacSecret();
                    $this->revealedHmacSecret = $result['plaintext'];
                    $this->data['hmac_masked'] = $result['masked'];

                    IntegrationSettingsAudit::query()->create([
                        'user_id' => auth()->id(),
                        'ip_address' => request()->ip(),
                        'summary' => 'Regenerated tenant webhook HMAC secret',
                        'context' => ['tenant_id' => TenantResolver::requiredTenantId()],
                    ]);

                    Notification::make()
                        ->title('HMAC secret regenerated')
                        ->body('Copy the secret from the yellow box below. Store it in your PBX/integration — not in email or chat.')
                        ->warning()
                        ->send();
                }),
            Action::make('create_api_token')
                ->label('Create REST token')
                ->icon('heroicon-o-finger-print')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Create personal access token?')
                ->modalDescription('For external REST integrations (/api/v1). Token is shown once.')
                ->action(function (): void {
                    $user = auth()->user();
                    abort_if($user === null, 403);

                    $token = $user->createToken(
                        'integration-' . now()->format('Y-m-d-His'),
                        ['*'],
                    );

                    $this->revealedSanctumToken = $token->plainTextToken;

                    Notification::make()
                        ->title('API token created')
                        ->body('Copy the token from the yellow box below. It cannot be retrieved later.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save tenant settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $api = app(TenantApiSettingsService::class);
        $tenantId = TenantResolver::requiredTenantId();

        $api->saveApiHost($tenantId, (string) ($state['api_host'] ?? ''));

        IntegrationSettingsAudit::query()->create([
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'summary' => 'Updated tenant API host',
            'context' => [
                'tenant_id' => $tenantId,
                'api_host' => $api->apiHost($tenantId),
            ],
        ]);

        $this->data['hmac_masked'] = $api->maskedWebhookHmacSecret() ?: '— not set —';

        Notification::make()
            ->title('API settings saved')
            ->success()
            ->send();
    }
}
