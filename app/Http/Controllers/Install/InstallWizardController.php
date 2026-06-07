<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\Installer\InstallerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InstallWizardController extends Controller
{
    public function __construct(
        private readonly InstallerService $installer,
    ) {}

    public function welcome(): View
    {
        return view('install.welcome', [
            'requirements' => $this->installer->requirements(),
            'documentRoot' => $this->installer->documentRootHint(),
            'laravelRoot' => $this->installer->laravelRoot(),
        ]);
    }

    public function permissions(): View|RedirectResponse
    {
        $requirements = $this->installer->requirements();
        if (! $requirements['ok']) {
            return redirect()->route('install.welcome');
        }

        return view('install.permissions', [
            'permissions' => $this->installer->permissionStatus(),
            'permissionsOk' => $this->installer->permissionsOk(),
        ]);
    }

    public function fixPermissions(Request $request): RedirectResponse
    {
        $this->installer->fixPermissions();

        return redirect()
            ->route('install.permissions')
            ->with('status', 'Permissions updated. If any folder is still red, fix ownership in cPanel File Manager.');
    }

    public function database(): View|RedirectResponse
    {
        if (! $this->installer->permissionsOk()) {
            return redirect()->route('install.permissions');
        }

        return view('install.database', [
            'defaults' => [
                'db_driver' => 'mysql',
                'db_host' => '127.0.0.1',
                'db_port' => '3306',
            ],
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'db_driver' => ['required', 'in:mysql,pgsql'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:10'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $test = $this->installer->testDatabase([
            'driver' => $validated['db_driver'],
            'host' => $validated['db_host'],
            'port' => $validated['db_port'] ?? '',
            'database' => $validated['db_database'],
            'username' => $validated['db_username'],
            'password' => $validated['db_password'] ?? '',
        ]);

        if (! $test['ok']) {
            return back()
                ->withInput()
                ->withErrors(['db_database' => $test['message']]);
        }

        $this->installer->saveDatabaseConfig($validated);
        $request->session()->put('install.database', $validated);

        return redirect()->route('install.admin');
    }

    public function admin(): View|RedirectResponse
    {
        if (! session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        $scheme = request()->isSecure() ? 'https' : 'http';

        return view('install.admin', [
            'defaults' => [
                'app_url' => $scheme.'://'.request()->getHost(),
                'app_name' => 'ISP Platform',
                'company_name' => 'My ISP Company',
            ],
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        if (! $request->session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        $validated = $request->validate([
            'app_url' => ['required', 'url', 'max:255'],
            'app_name' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $this->installer->saveSiteConfig($validated);

        $result = $this->installer->runInstallation();

        if (! $result['ok']) {
            return back()
                ->withInput()
                ->withErrors(['admin_email' => $result['message']]);
        }

        $request->session()->forget('install.database');

        return redirect()->route('install.complete');
    }

    public function complete(): View|RedirectResponse
    {
        if (! \App\Support\AppInstalled::isInstalled()) {
            return redirect()->route('install.welcome');
        }

        return view('install.complete', [
            'adminUrl' => rtrim((string) config('app.url'), '/').'/admin',
            'adminEmail' => (string) config('isp.admin_email'),
        ]);
    }
}
