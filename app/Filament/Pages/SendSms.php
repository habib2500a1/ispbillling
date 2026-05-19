<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Services\Notifications\MessageTemplateRenderer;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use App\Support\NotificationEvent;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
class SendSms extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string $view = 'filament.pages.send-sms';

    protected static ?string $navigationLabel = 'Send SMS';

    protected static ?string $title = 'Send SMS';

    protected static ?string $slug = 'send-sms';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
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

        $this->form->fill([
            'customer_id' => null,
            'template_key' => null,
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
                        Section::make('Send single SMS')
                            ->description('Send a one-off SMS to one subscriber. Delivery is logged under SMS Report.')
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Subscriber')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return Customer::query()
                                            ->where('status', '!=', 'cancelled')
                                            ->where(function ($query) use ($search): void {
                                                $query->where('name', 'like', "%{$search}%")
                                                    ->orWhere('customer_code', 'like', "%{$search}%")
                                                    ->orWhere('phone', 'like', "%{$search}%");
                                            })
                                            ->orderBy('name')
                                            ->limit(30)
                                            ->get()
                                            ->mapWithKeys(fn (Customer $record): array => [
                                                $record->id => "{$record->name} · {$record->customer_code} · ".($record->phone ?: 'no phone'),
                                            ])
                                            ->all();
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->name)
                                    ->required()
                                    ->live(),
                                Select::make('template_key')
                                    ->label('Use template (optional)')
                                    ->options(static::templateOptions())
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                        if (! filled($state)) {
                                            return;
                                        }

                                        $customer = Customer::query()->find($get('customer_id'));
                                        $set('message', MessageTemplateRenderer::render($state, [
                                            'name' => $customer?->name ?? 'Customer',
                                        ]));
                                    }),
                                Textarea::make('message')
                                    ->label('Message')
                                    ->required()
                                    ->rows(5)
                                    ->maxLength(640)
                                    ->helperText('Max 640 characters. Use templates for payment, due, outage, OTP text.'),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function templateOptions(): array
    {
        $templates = config('notifications.templates', []);
        $labels = NotificationEvent::labels();
        $options = [];

        foreach ($templates as $key => $value) {
            if (! is_string($key) || $value === '') {
                continue;
            }
            $options[$key] = $labels[$key] ?? str($key)->headline()->toString();
        }

        return $options;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Send SMS')
                ->icon('heroicon-o-paper-airplane')
                ->submit('send'),
        ];
    }

    public function send(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $customer = Customer::query()->findOrFail($state['customer_id']);

        if (! filled($customer->phone)) {
            Notification::make()
                ->title('No mobile number')
                ->body('Add a phone number on the subscriber profile first.')
                ->danger()
                ->send();

            return;
        }

        if (! (bool) config('notifications.sms.enabled', false)) {
            Notification::make()
                ->title('SMS gateway is disabled')
                ->body('Enable SMS under Gateway Tester first.')
                ->warning()
                ->send();

            return;
        }

        $message = trim((string) ($state['message'] ?? ''));
        $tenantId = (int) ($customer->tenant_id ?? TenantResolver::requiredTenantId());

        app(NotificationDispatcher::class)->send(
            $tenantId,
            (int) $customer->id,
            filled($state['template_key'] ?? null) ? (string) $state['template_key'] : 'promotional',
            NotificationChannel::SMS,
            (string) $customer->phone,
            $message,
            ['subject' => 'SMS from '.config('app.name')],
        );

        Notification::make()
            ->title('SMS queued')
            ->body("Message sent to {$customer->phone}. Check SMS Report for status.")
            ->success()
            ->send();

        $this->form->fill([
            'customer_id' => $customer->id,
            'template_key' => $state['template_key'] ?? null,
            'message' => '',
        ]);
    }
}
