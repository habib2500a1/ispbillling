<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Admin login screen (GET). Form submits via POST to admin.login.session — no Livewire on submit.
 */
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
}
