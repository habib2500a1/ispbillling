<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Services\Staff\ActivityLogger;
use App\Services\Staff\IpAccessGuard;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class AdminLogin extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.admin-login';

    protected static string $layout = 'filament.layouts.auth-split';

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return '';
    }

    public function getSubHeading(): ?string
    {
        return null;
    }

    public function hasLogo(): bool
    {
        return false;
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Username')
            ->autocomplete('username')
            ->required()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = trim((string) ($data['email'] ?? ''));

        if (! str_contains($login, '@')) {
            if (strtolower($login) === 'admin') {
                $login = (string) config('isp.admin_email');
            } else {
                $user = User::query()
                    ->withoutGlobalScopes()
                    ->where(fn ($q) => $q->where('email', $login)->orWhere('name', $login))
                    ->first();
                $login = $user?->email ?? $login;
            }
        }

        return [
            'email' => $login,
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            app(ActivityLogger::class)->log(
                'login.failed',
                'Failed staff login attempt',
                null,
                ['login' => $data['email'] ?? ''],
            );
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (! $user instanceof FilamentUser || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            Filament::auth()->logout();
            throw ValidationException::withMessages([
                'data.email' => 'This account has no panel access. Ask admin to assign a staff role (Admin, Branch Manager, Cashier, NOC, etc.).',
            ]);
        }

        if ($user->is_active === false) {
            Filament::auth()->logout();
            throw ValidationException::withMessages([
                'data.email' => 'This account has been deactivated. Contact an administrator.',
            ]);
        }

        $ip = request()->ip();
        if (! app(IpAccessGuard::class)->allows($user, $ip)) {
            Filament::auth()->logout();
            throw ValidationException::withMessages([
                'data.email' => 'Login is not allowed from your IP address.',
            ]);
        }

        session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            session(['staff.2fa_verified' => false]);
        } else {
            session(['staff.2fa_verified' => true]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        app(ActivityLogger::class)->log('login', 'Staff signed in', $user);

        return app(LoginResponse::class);
    }
}
