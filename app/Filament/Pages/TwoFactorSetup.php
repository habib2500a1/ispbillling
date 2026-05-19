<?php

namespace App\Filament\Pages;

use App\Services\Staff\ActivityLogger;
use App\Services\Staff\TwoFactorService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TwoFactorSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string $view = 'filament.pages.two-factor-setup';

    protected static ?string $slug = 'two-factor-setup';

    protected static ?string $title = 'Two-factor authentication';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $pendingSecret = null;

    public ?string $qrUrl = null;

    /** @var list<string> */
    public array $recoveryCodes = [];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && ! $user->hasTwoFactorEnabled()) {
            $this->pendingSecret = app(TwoFactorService::class)->generateSecret();
            $this->qrUrl = app(TwoFactorService::class)->getQrCodeUrl($user, $this->pendingSecret);
        }
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Confirm with 6-digit code')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function enable(): void
    {
        $user = auth()->user();
        if ($user === null || $this->pendingSecret === null) {
            return;
        }

        $codes = app(TwoFactorService::class)->enable(
            $user,
            $this->pendingSecret,
            (string) ($this->data['code'] ?? ''),
        );

        if ($codes === false) {
            Notification::make()->title('Invalid code')->danger()->send();

            return;
        }

        $this->recoveryCodes = $codes;
        $this->pendingSecret = null;
        $this->qrUrl = null;
        session(['staff.2fa_verified' => true]);

        app(ActivityLogger::class)->log('2fa.enabled', 'Two-factor authentication enabled', $user);

        Notification::make()
            ->title('2FA enabled')
            ->body('Save your recovery codes — they are shown only once.')
            ->success()
            ->send();
    }

    public function disable(): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        app(TwoFactorService::class)->disable($user);
        session(['staff.2fa_verified' => true]);

        app(ActivityLogger::class)->log('2fa.disabled', 'Two-factor authentication disabled', $user);

        Notification::make()->title('2FA disabled')->warning()->send();

        $this->redirect(static::getUrl());
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = auth()->user();
        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            return;
        }

        $this->recoveryCodes = app(TwoFactorService::class)->regenerateRecoveryCodes($user);

        Notification::make()->title('New recovery codes generated')->success()->send();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
