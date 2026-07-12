<?php

namespace App\Http\Controllers;

use App\Livewire\MikrotikSync;
use App\Models\RouterList;
use App\Services\Mikrotik\MikrotikPppImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MikrotikImportController extends Controller
{
    public function show(int $id): View
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        $router = RouterList::findOrFail($id);
        $loadError = '';
        $secretList = [];

        try {
            if ($router->action !== 'connected') {
                $probe = app(MikrotikController::class)->checkConnection(
                    (string) $router->ip_address,
                    $router->ssh_port,
                    $router->api_port,
                    (string) $router->username,
                    (string) $router->password,
                    '/system/resource/print',
                    '/system resource print',
                    [],
                    false
                );
                if (empty($probe['status'])) {
                    $loadError = 'Router offline: '.($probe['message'] ?? 'unreachable');
                } else {
                    $router->action = 'connected';
                    $router->save();
                }
            }

            if ($loadError === '') {
                Cache::forever('mikrotik:cache_version:'.$router->router_name, time());
                $secrets = app(MikrotikPppImportService::class)->listSecretsFromRouter($router);
                $secretList = array_values(array_map(fn ($s) => [
                    'name' => (string) $s['name'],
                    'profile' => (string) ($s['profile'] ?? ''),
                    'disabled' => (bool) ($s['disabled'] ?? false),
                    'comment' => (string) ($s['comment'] ?? ''),
                ], $secrets));
            }
        } catch (\Throwable $e) {
            $loadError = $e->getMessage();
        }

        return view('mikrotik-import', [
            'router' => $router,
            'secretList' => $secretList,
            'secretTotal' => count($secretList),
            'loadError' => $loadError,
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        $router = RouterList::findOrFail($id);

        $validated = $request->validate([
            'usernames' => ['required', 'array', 'min:1', 'max:100'],
            'usernames.*' => ['required', 'string', 'max:255'],
            'create_missing' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
            'code_format' => ['nullable', 'string', 'in:prefix_sequential,secret_as_code,numeric'],
        ]);

        $names = array_values(array_unique(array_filter(array_map('trim', $validated['usernames']))));
        if ($names === []) {
            flash()->warning('Select at least one user.');

            return redirect()->route('mikrotik.import', $id);
        }

        try {
            $result = app(MikrotikPppImportService::class)->importSelectedFromRouter(
                $router,
                $names,
                [
                    'create_missing' => $request->boolean('create_missing', true),
                    'update_existing' => $request->boolean('update_existing', true),
                    'code_format' => $validated['code_format'] ?? 'prefix_sequential',
                ]
            );

            app(MikrotikSync::class)->refreshOnlineSessions($router->router_name);

            flash()->success(sprintf(
                'Import OK — Created: %d · Updated: %d · Skipped: %d · Users: %s',
                $result['created'],
                $result['updated'],
                $result['skipped'],
                implode(', ', array_slice($names, 0, 8)).(count($names) > 8 ? '…' : '')
            ));

            return redirect()->route('customers.index');
        } catch (\Throwable $e) {
            flash()->error('Import failed: '.$e->getMessage());
        }

        return redirect()->route('mikrotik.import', $id);
    }
}
