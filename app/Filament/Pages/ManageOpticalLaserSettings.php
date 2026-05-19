<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Support\OpticalThresholds;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

/**
 * @property Form $form
 */
class ManageOpticalLaserSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static string $view = 'filament.pages.manage-optical-laser-settings';

    protected static ?string $navigationLabel = 'Laser thresholds';

    protected static ?string $title = 'ONU laser power thresholds';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'optical-laser-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole('super-admin') || $user->can('network.manage'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $bands = OpticalThresholds::rxBands();

        $this->form->fill([
            'rx_excellent_max' => $bands['excellent_max'],
            'rx_excellent_min' => $bands['excellent_min'],
            'rx_good_min' => $bands['good_min'],
            'rx_weak_min' => $bands['weak_min'],
            'rx_high_warn_above' => OpticalThresholds::rxHighWarnAbove(),
            'tx_normal_min' => OpticalThresholds::txNormalMin(),
            'tx_normal_max' => OpticalThresholds::txNormalMax(),
            'tx_high_warn_above' => OpticalThresholds::txHighWarnAbove(),
            'sudden_drop_db' => (float) config('optical.sudden_drop_db', 3),
            'alert_on_high_rx' => (bool) config('optical.alert_on_high_rx', true),
            'alert_on_high_tx' => (bool) config('optical.alert_on_high_tx', true),
            'auto_ticket_enabled' => (bool) config('optical.auto_ticket_enabled', true),
            'notify_ops' => (bool) config('optical.notify_ops', true),
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
                        Section::make('RX laser (OLT → subscriber)')
                            ->description('dBm values: higher number = stronger signal. “Laser high” when RX is above the high threshold (often short fibre or dirty connector).')
                            ->schema([
                                TextInput::make('rx_high_warn_above')
                                    ->label('Laser high — RX above (dBm)')
                                    ->helperText('যেমন -8: এর চেয়ে বেশি (যেমন -0.5) হলে “Laser high” দেখাবে ও alert হতে পারে।')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                                TextInput::make('rx_excellent_max')
                                    ->label('Excellent — upper bound (dBm)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                                TextInput::make('rx_excellent_min')
                                    ->label('Excellent — lower bound (dBm)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                                TextInput::make('rx_good_min')
                                    ->label('Good — lower bound (dBm)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                                TextInput::make('rx_weak_min')
                                    ->label('Weak — lower bound / critical below (dBm)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                            ])
                            ->columns(2),
                        Section::make('TX laser (ONU transmit)')
                            ->schema([
                                TextInput::make('tx_normal_min')
                                    ->label('TX normal — minimum (dBm)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                                TextInput::make('tx_normal_max')
                                    ->label('TX normal — maximum (dBm)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                                TextInput::make('tx_high_warn_above')
                                    ->label('Laser high — TX above (dBm)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->required(),
                            ])
                            ->columns(2),
                        Section::make('Alerts & tickets')
                            ->schema([
                                Toggle::make('alert_on_high_rx')
                                    ->label('Alert when RX laser is high'),
                                Toggle::make('alert_on_high_tx')
                                    ->label('Alert when TX laser is high'),
                                TextInput::make('sudden_drop_db')
                                    ->label('Sudden RX drop alert (dB)')
                                    ->numeric()
                                    ->step(0.5)
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->required(),
                                Toggle::make('auto_ticket_enabled')
                                    ->label('Auto-create ticket on critical optical alert'),
                                Toggle::make('notify_ops')
                                    ->label('Notify ops (Telegram template)'),
                            ])
                            ->columns(1),
                        Section::make('Preview')
                            ->schema([
                                Placeholder::make('band_preview')
                                    ->label('Current bands (after save)')
                                    ->content(fn (): HtmlString => new HtmlString(
                                        '<p class="text-sm text-gray-600 dark:text-gray-400">'.e(OpticalThresholds::bandSummaryText()).'</p>'
                                    )),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save thresholds')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('reset_defaults')
                ->label('Reset to .env defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $keys = [
                        'optical.rx_thresholds.excellent_max',
                        'optical.rx_thresholds.excellent_min',
                        'optical.rx_thresholds.good_min',
                        'optical.rx_thresholds.weak_min',
                        'optical.rx_high_warn_above',
                        'optical.tx_normal_min',
                        'optical.tx_normal_max',
                        'optical.tx_high_warn_above',
                        'optical.sudden_drop_db',
                        'optical.alert_on_high_rx',
                        'optical.alert_on_high_tx',
                        'optical.auto_ticket_enabled',
                        'optical.notify_ops',
                    ];
                    AppSetting::query()->whereIn('key', $keys)->delete();
                    foreach ($keys as $key) {
                        AppSetting::restoreConfigKeyFromEnv($key);
                    }
                    AppSetting::syncToRuntimeConfig();
                    $this->mount();
                    Notification::make()->title('Thresholds reset to environment defaults')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $this->putFloat('optical.rx_high_warn_above', $state['rx_high_warn_above'] ?? -8);
        $this->putFloat('optical.rx_thresholds.excellent_max', $state['rx_excellent_max'] ?? -8);
        $this->putFloat('optical.rx_thresholds.excellent_min', $state['rx_excellent_min'] ?? -15);
        $this->putFloat('optical.rx_thresholds.good_min', $state['rx_good_min'] ?? -22);
        $this->putFloat('optical.rx_thresholds.weak_min', $state['rx_weak_min'] ?? -27);
        $this->putFloat('optical.tx_normal_min', $state['tx_normal_min'] ?? 0.5);
        $this->putFloat('optical.tx_normal_max', $state['tx_normal_max'] ?? 5.5);
        $this->putFloat('optical.tx_high_warn_above', $state['tx_high_warn_above'] ?? 5.5);
        $this->putFloat('optical.sudden_drop_db', $state['sudden_drop_db'] ?? 3);

        AppSetting::putValue('optical.alert_on_high_rx', $this->truthy($state['alert_on_high_rx'] ?? true) ? '1' : '0');
        AppSetting::putValue('optical.alert_on_high_tx', $this->truthy($state['alert_on_high_tx'] ?? true) ? '1' : '0');
        AppSetting::putValue('optical.auto_ticket_enabled', $this->truthy($state['auto_ticket_enabled'] ?? true) ? '1' : '0');
        AppSetting::putValue('optical.notify_ops', $this->truthy($state['notify_ops'] ?? true) ? '1' : '0');

        AppSetting::syncToRuntimeConfig();

        Notification::make()
            ->title('Laser thresholds saved')
            ->body(OpticalThresholds::bandSummaryText())
            ->success()
            ->send();
    }

    private function putFloat(string $key, mixed $value): void
    {
        AppSetting::putValue($key, (string) (float) $value);
    }

    private function truthy(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
