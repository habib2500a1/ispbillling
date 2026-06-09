<?php

namespace App\Filament\Pages;

use App\Models\SmsCampaign;
use App\Services\Notifications\Channels\WhatsAppNotificationChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * @property Form $form
 */
class BulkSmsCampaign extends Page implements HasTable
{
    use InteractsWithFormActions;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.pages.bulk-sms-campaign';

    protected static ?string $navigationLabel = 'Bulk SMS / WhatsApp';

    protected static ?string $title = 'Bulk SMS, WhatsApp & email';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 12;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canSms();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill([
            'name' => 'Campaign '.now()->format('Y-m-d H:i'),
            'channel' => 'sms',
            'target' => 'active',
            'message' => '',
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
                        Section::make('New campaign')
                            ->schema([
                                TextInput::make('name')->required()->maxLength(120),
                                Select::make('channel')
                                    ->options(fn (): array => self::channelOptions())
                                    ->required()
                                    ->live()
                                    ->native(false),
                                Placeholder::make('whatsapp_setup_hint')
                                    ->label('')
                                    ->content(fn (): string => self::whatsAppConfigured()
                                        ? 'WhatsApp Cloud API is connected — bulk messages go to each subscriber’s WhatsApp number (primary phone or WhatsApp contact).'
                                        : 'WhatsApp is not configured. Set Phone Number ID + Access Token under Notifications settings to enable bulk WhatsApp.')
                                    ->visible(fn (Get $get): bool => $get('channel') === NotificationChannel::WHATSAPP),
                                Select::make('target')
                                    ->options([
                                        'active' => 'Active subscribers',
                                        'due' => 'Subscribers with due bills',
                                        'suspended' => 'Suspended only',
                                        'all' => 'All subscribers',
                                    ])
                                    ->required()
                                    ->native(false),
                                Textarea::make('message')
                                    ->required()
                                    ->rows(4)
                                    ->maxLength(500)
                                    ->helperText(fn (Get $get): string => match ((string) $get('channel')) {
                                        NotificationChannel::WHATSAPP => 'Plain text via WhatsApp Cloud API. Subscribers without a valid number are skipped. Marketing blasts may need Meta-approved templates.',
                                        NotificationChannel::EMAIL => 'Email body sent to each subscriber with a valid email address.',
                                        default => 'Plain text SMS sent to each matching subscriber phone number.',
                                    }),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SmsCampaign::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\TextColumn::make('target'),
                Tables\Columns\TextColumn::make('recipient_count')->label('Sent'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('sent_at')->dateTime(),
            ])
            ->paginated([10, 25]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Send campaign')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->action('sendCampaign'),
        ];
    }

    public function sendCampaign(NotificationDispatcher $dispatcher): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $message = trim((string) ($state['message'] ?? ''));
        if ($message === '') {
            Notification::make()->title('Message required')->danger()->send();

            return;
        }

        $tenantId = TenantResolver::requiredTenantId();
        $channel = (string) ($state['channel'] ?? NotificationNotificationChannel::SMS);
        $target = (string) ($state['target'] ?? 'active');

        if ($channel === NotificationChannel::WHATSAPP && ! self::whatsAppConfigured()) {
            Notification::make()
                ->title('WhatsApp not configured')
                ->body('Enable WhatsApp Cloud API in Notifications settings (Phone Number ID + Access Token).')
                ->warning()
                ->send();

            return;
        }

        $count = $dispatcher->broadcastCustom($tenantId, $message, $target, $channel);

        SmsCampaign::query()->create([
            'tenant_id' => $tenantId,
            'created_by' => auth()->id(),
            'name' => (string) ($state['name'] ?? 'Campaign'),
            'message' => $message,
            'channel' => $channel,
            'target' => $target,
            'status' => 'sent',
            'recipient_count' => $count,
            'sent_at' => now(),
        ]);

        $channelLabel = NotificationChannel::labels()[$channel] ?? $channel;

        Notification::make()
            ->title("{$channelLabel} campaign queued for {$count} recipient(s)")
            ->body($count === 0
                ? 'No matching subscribers had a valid number/address for this channel.'
                : 'Delivery status appears in campaign history and notification logs.')
            ->success()
            ->send();

        $this->form->fill([
            'name' => 'Campaign '.now()->format('Y-m-d H:i'),
            'channel' => $channel,
            'target' => $target,
            'message' => '',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function channelOptions(): array
    {
        $options = [
            NotificationChannel::SMS => 'SMS',
            NotificationChannel::EMAIL => 'Email',
        ];

        if (self::whatsAppConfigured()) {
            $options[NotificationChannel::WHATSAPP] = 'WhatsApp';
        }

        return $options;
    }

    private static function whatsAppConfigured(): bool
    {
        return (new WhatsAppNotificationChannel)->isEnabled();
    }
}
