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
use Illuminate\Contracts\Support\Htmlable;

class TwoFactorChallenge extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.two-factor-challenge';

    protected static ?string $slug = 'two-factor-challenge';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return 'Two-factor verification';
    }

    public function mount(): void
    {
        $user = auth()->user();
        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            redirect()->to(url('/admin'));

            return;
        }

        if (session('staff.2fa_verified') === true) {
            redirect()->to(url('/admin'));
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Authentication code')
                    ->required()
                    ->autocomplete('one-time-code')
                    ->placeholder('000000'),
            ])
            ->statePath('data');
    }

    public function verify(): void
    {
        $code = $this->data['code'] ?? '';
        $user = auth()->user();

        if ($user === null || ! app(TwoFactorService::class)->verify($user, (string) $code)) {
            Notification::make()
                ->title('Invalid code')
                ->body('Check your authenticator app or use a recovery code.')
                ->danger()
                ->send();

            return;
        }

        session(['staff.2fa_verified' => true]);
        app(ActivityLogger::class)->log('2fa.verified', 'Two-factor challenge passed', $user);

        Notification::make()->title('Verified')->success()->send();

        $this->redirect(url('/admin'));
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
