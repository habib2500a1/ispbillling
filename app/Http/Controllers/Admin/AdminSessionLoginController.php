<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Staff\ActivityLogger;
use App\Services\Staff\StaffLoginService;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Primary admin login — standard HTML form POST (no Livewire required).
 */
class AdminSessionLoginController extends Controller
{
    public function __invoke(Request $request, StaffLoginService $login): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $result = $login->attempt(
            $validated['email'],
            $validated['password'],
            (bool) ($validated['remember'] ?? false),
            $request->ip(),
        );

        if (! $result['ok']) {
            app(ActivityLogger::class)->log(
                'login.failed',
                'Failed staff login attempt',
                null,
                ['login' => $validated['email']],
            );

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => $result['error']]);
        }

        app(ActivityLogger::class)->log('login', 'Staff signed in', Filament::auth()->user());

        return app(LoginResponse::class)->toResponse($request);
    }
}
