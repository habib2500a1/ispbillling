<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Ai\AiSettingsService;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
class ManageAiSettings extends Page
{
    use HidesHubNavigation;
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.pages.manage-ai-settings';

    protected static ?string $navigationLabel = 'AI settings';

    protected static ?string $title = 'AI & Copilot settings';

    protected static ?string $slug = 'ai-settings';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager']) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = app(AiSettingsService::class);
        $tenantId = TenantResolver::requiredTenantId();

        $this->form->fill([
            'enabled' => $settings->isEnabled($tenantId),
            'llm_enabled' => $settings->llmEnabled($tenantId),
            'llm_api_key' => '',
            'bengali_replies' => $settings->bengaliReplies($tenantId),
            'mask_pii' => $settings->maskPii($tenantId),
            'customer_ai_enabled' => $settings->customerAiEnabled($tenantId),
            'reseller_ai_enabled' => $settings->resellerAiEnabled($tenantId),
            'proactive_digest_enabled' => $settings->proactiveDigestEnabled($tenantId),
            'rag_enabled' => $settings->ragEnabled($tenantId),
            'actions_enabled' => $settings->actionsEnabled($tenantId),
            'daily_query_limit' => $settings->dailyQueryLimit($tenantId),
            'allowed_tools' => $settings->allowedTools($tenantId),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Core')
                            ->schema([
                                Toggle::make('enabled')
                                    ->label('AI Copilot enabled')
                                    ->default(true),
                                Toggle::make('bengali_replies')
                                    ->label('Bengali replies when query is in Bangla')
                                    ->default(true),
                                Toggle::make('mask_pii')
                                    ->label('Mask phone numbers in audit logs')
                                    ->default(true),
                                TextInput::make('daily_query_limit')
                                    ->label('Daily staff query limit')
                                    ->numeric()
                                    ->minValue(10)
                                    ->maxValue(10000)
                                    ->default(500),
                            ])
                            ->columns(2),
                        Section::make('Optional LLM')
                            ->description('OpenAI-compatible API for tool routing and richer summaries. Falls back to rule-based intents when disabled.')
                            ->schema([
                                Toggle::make('llm_enabled')
                                    ->label('Enable LLM gateway'),
                                TextInput::make('llm_api_key')
                                    ->label('LLM API key')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Leave blank to keep the current key. Stored per tenant in settings.'),
                            ])
                            ->columns(1),
                        Section::make('Channels')
                            ->schema([
                                Toggle::make('customer_ai_enabled')
                                    ->label('Customer / mobile AI assistant'),
                                Toggle::make('reseller_ai_enabled')
                                    ->label('Reseller portal AI'),
                                Toggle::make('proactive_digest_enabled')
                                    ->label('Daily proactive digest (Telegram)'),
                                Toggle::make('rag_enabled')
                                    ->label('Knowledge base (RAG) search'),
                                Toggle::make('actions_enabled')
                                    ->label('Human-in-the-loop action proposals'),
                            ])
                            ->columns(2),
                        Section::make('Tool allowlist')
                            ->description('Leave empty to allow all built-in tools.')
                            ->schema([
                                TagsInput::make('allowed_tools')
                                    ->label('Allowed tools')
                                    ->placeholder('billing.due_customers'),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('actionQueue')
                ->label('Action approvals')
                ->icon('heroicon-o-check-badge')
                ->url(AiActionApprovalHub::getUrl())
                ->visible(fn (): bool => app(AiSettingsService::class)->actionsEnabled()),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $tenantId = TenantResolver::requiredTenantId();
        $overrides = [
            'enabled' => $this->truthy($state['enabled'] ?? true),
            'bengali_replies' => $this->truthy($state['bengali_replies'] ?? true),
            'mask_pii' => $this->truthy($state['mask_pii'] ?? true),
            'customer_ai_enabled' => $this->truthy($state['customer_ai_enabled'] ?? true),
            'reseller_ai_enabled' => $this->truthy($state['reseller_ai_enabled'] ?? true),
            'proactive_digest_enabled' => $this->truthy($state['proactive_digest_enabled'] ?? true),
            'rag_enabled' => $this->truthy($state['rag_enabled'] ?? true),
            'actions_enabled' => $this->truthy($state['actions_enabled'] ?? true),
            'daily_query_limit' => max(10, (int) ($state['daily_query_limit'] ?? 500)),
            'allowed_tools' => array_values(array_filter(array_map('strval', $state['allowed_tools'] ?? []))),
            'llm' => [
                'enabled' => $this->truthy($state['llm_enabled'] ?? false),
            ],
        ];

        $apiKey = trim((string) ($state['llm_api_key'] ?? ''));
        if ($apiKey !== '') {
            $overrides['llm']['api_key'] = $apiKey;
        }

        app(AiSettingsService::class)->saveTenantOverrides($tenantId, $overrides);

        Notification::make()
            ->title('AI settings saved')
            ->success()
            ->send();
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }
}
