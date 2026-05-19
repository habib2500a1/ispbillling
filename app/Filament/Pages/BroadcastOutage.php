<?php

namespace App\Filament\Pages;

use App\Services\Notifications\NotificationDispatcher;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
class BroadcastOutage extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static string $view = 'filament.pages.broadcast-outage';

    protected static ?string $navigationLabel = 'Outage broadcast';

    protected static ?string $title = 'Outage / maintenance notice';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['subject' => 'Service notice', 'message' => '']);
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
                        Section::make('Broadcast to active subscribers')
                            ->description('Sends via channels configured under Notifications → Outage (email, SMS, WhatsApp). Also posts to Telegram ops if enabled.')
                            ->schema([
                                TextInput::make('subject')
                                    ->label('Internal reference')
                                    ->maxLength(120)
                                    ->default('Service notice'),
                                Textarea::make('message')
                                    ->label('Customer message')
                                    ->required()
                                    ->rows(5)
                                    ->placeholder('e.g. Fiber maintenance in your area from 2 AM–6 AM. Service may be intermittent.')
                                    ->helperText('Included in the outage template as {message}'),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Send outage notification')
                ->color('warning')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Confirm outage broadcast')
                ->modalDescription('This will notify all active subscribers on this tenant. Continue?')
                ->submit('send'),
        ];
    }

    public function send(NotificationDispatcher $dispatcher): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $message = trim((string) ($state['message'] ?? ''));
        if ($message === '') {
            Notification::make()->title('Message is required')->danger()->send();

            return;
        }

        $count = $dispatcher->broadcastOutage(TenantResolver::requiredTenantId(), $message);

        Notification::make()
            ->title("Outage broadcast queued for {$count} subscriber(s)")
            ->success()
            ->send();

        $this->form->fill(['subject' => $state['subject'] ?? 'Service notice', 'message' => '']);
    }
}
