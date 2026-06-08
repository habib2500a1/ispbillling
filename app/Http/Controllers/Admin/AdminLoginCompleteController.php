<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One-hop after POST login so browsers commit session cookies before /admin Livewire boot.
 */
final class AdminLoginCompleteController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user('web') === null) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => __('Your session could not be started. Clear site cookies and try again.')]);
        }

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();

        $target = Filament::getUrl();

        return view('admin.login-complete', [
            'target' => $target,
        ]);
    }
}
