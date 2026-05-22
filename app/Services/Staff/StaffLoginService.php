<?php

namespace App\Services\Staff;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\RateLimiter;

class StaffLoginService
{
    /**
     * @return array{ok: bool, error: ?string}
     */
    public function attempt(string $login, string $password, bool $remember, ?string $ip): array
    {
        $ip = $ip ?? '0.0.0.0';
        $throttleKey = 'staff-login:'.sha1($ip.'|'.strtolower(trim($login)));

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return [
                'ok' => false,
                'error' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ];
        }

        $credentials = $this->resolveCredentials($login, $password);

        if (! Filament::auth()->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            return [
                'ok' => false,
                'error' => __('These credentials do not match our records.'),
            ];
        }

        $user = Filament::auth()->user();

        if (! $user instanceof FilamentUser || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            Filament::auth()->logout();
            RateLimiter::hit($throttleKey, 60);

            return [
                'ok' => false,
                'error' => 'This account has no panel access. Ask admin to assign a staff role (Admin, Branch Manager, Cashier, NOC, etc.).',
            ];
        }

        if ($user->is_active === false) {
            Filament::auth()->logout();
            RateLimiter::hit($throttleKey, 60);

            return [
                'ok' => false,
                'error' => 'This account has been deactivated. Contact an administrator.',
            ];
        }

        if (! app(IpAccessGuard::class)->allows($user, $ip)) {
            Filament::auth()->logout();
            RateLimiter::hit($throttleKey, 60);

            return [
                'ok' => false,
                'error' => 'Login is not allowed from your IP address.',
            ];
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();
        session(['staff.2fa_verified' => $user->hasTwoFactorEnabled() ? false : true]);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        return ['ok' => true, 'error' => null];
    }

    /**
     * @return array{email: string, password: string}
     */
    public function resolveCredentials(string $login, string $password): array
    {
        $login = trim($login);

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
            'password' => $password,
        ];
    }
}
