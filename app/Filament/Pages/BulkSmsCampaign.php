<?php

namespace App\Filament\Pages;

use App\Models\SmsCampaign;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
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

    protected static ?string $navigationLabel = 'Bulk SMS / email';

    protected static ?string $title = 'Bulk SMS & email';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 12;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager']) ?? false;
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
                                    ->options([
                                        NotificationChannel::SMS => 'SMS',
                                        NotificationChannel::EMAIL => 'Email',
                                    ])
                                    ->required()
                                    ->native(false),
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
                                    ->helperText('Plain text message sent to each matching subscriber.'),
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
        $channel = (string) ($state['channel'] ?? NotificationChannel::SMS);
        $target = (string) ($state['target'] ?? 'active');

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

        Notification::make()
            ->title("Campaign sent to {$count} recipient(s)")
            ->success()
            ->send();

        $this->form->fill([
            'name' => 'Campaign '.now()->format('Y-m-d H:i'),
            'channel' => $channel,
            'target' => $target,
            'message' => '',
        ]);
    }
}
