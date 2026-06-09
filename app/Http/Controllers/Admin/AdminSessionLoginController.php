<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Staff\ActivityLogger;
use App\Services\Staff\StaffLoginService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Primary admin login — standard HTML form POST (no Livewire required).
 */
class AdminSessionLoginController extends Controller
{
    public function __invoke(Request $request, StaffLoginService $staffLogin): RedirectResponse
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'max:255', 'required_without:login'],
            'login' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'in:0,1,true,false,on,off,yes,no'],
        ]);

        $remember = filter_var($validated['remember'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $identifier = trim((string) ($validated['email'] ?? $validated['login'] ?? ''));

        $result = $staffLogin->attempt(
            $identifier,
            $validated['password'],
            $remember,
            $request->ip(),
        );

        if (! $result['ok']) {
            app(ActivityLogger::class)->log(
                'login.failed',
                'Failed staff login attempt',
                null,
                ['login' => $identifier],
            );

            return back()
                ->withInput($request->only('email', 'login', 'remember'))
                ->withErrors(['email' => $result['error']]);
        }

        app(ActivityLogger::class)->log('login', 'Staff signed in', auth('web')->user());

        $request->session()->save();

        return redirect()->to('/admin', status: 303);
    }
}
