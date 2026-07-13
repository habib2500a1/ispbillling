<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\RouterList;
use App\Services\Mikrotik\MikrotikPppImportService;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Import page: PPP list stays in Alpine (not Livewire state) to avoid max_input_vars.
 */
class MikrotikImportUsers extends Component
{
    public int $routerId;

    public string $routerName = '';

    public int $secretTotal = 0;

    public bool $createMissing = true;

    public bool $updateExisting = true;

    public string $codeFormat = 'prefix_sequential';

    public string $loadError = '';

    public string $lastMessage = '';

    public string $lastMessageType = ''; // success|warning|danger

    /** @var list<array{name: string, profile: string, disabled: bool, comment: string}> */
    protected array $secretList = [];

    public function mount(int $id): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->routerId = $id;
        $this->loadFromRouter();
    }

    public function loadFromRouter(): void
    {
        $this->loadError = '';
        $this->lastMessage = '';
        $this->secretList = [];

        $router = RouterList::find($this->routerId);
        if (! $router) {
            $this->loadError = 'Router not found.';

            return;
        }

        $this->routerName = (string) $router->router_name;

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
                    $this->loadError = 'Router offline: '.($probe['message'] ?? 'unreachable');

                    return;
                }
                $router->action = 'connected';
                $router->save();
            }

            Cache::forever('mikrotik:cache_version:'.$router->router_name, time());

            $secrets = app(MikrotikPppImportService::class)->listSecretsFromRouter($router);
            $this->secretList = array_values(array_map(fn ($s) => [
                'name' => (string) $s['name'],
                'profile' => (string) ($s['profile'] ?? ''),
                'disabled' => (bool) ($s['disabled'] ?? false),
                'comment' => (string) ($s['comment'] ?? ''),
            ], $secrets));
            $this->secretTotal = count($this->secretList);
        } catch (\Throwable $e) {
            $this->loadError = $e->getMessage();
            $this->secretTotal = 0;
        }
    }

    /**
     * @param  list<string>|string  $names
     */
    public function importNames(array|string $names = [])
    {
        if (is_string($names)) {
            $names = [$names];
        }

        $names = array_values(array_filter(array_map(
            fn ($n) => trim((string) $n),
            $names
        )));

        if ($names === []) {
            $this->lastMessageType = 'warning';
            $this->lastMessage = 'Select at least one user first.';

            return;
        }

        $router = RouterList::find($this->routerId);
        if (! $router) {
            $this->lastMessageType = 'danger';
            $this->lastMessage = 'Router not found.';

            return;
        }

        try {
            $result = app(MikrotikPppImportService::class)->importSelectedFromRouter(
                $router,
                $names,
                [
                    'create_missing' => $this->createMissing,
                    'update_existing' => $this->updateExisting,
                    'code_format' => $this->codeFormat,
                ]
            );

            app(MikrotikSync::class)->refreshOnlineSessions($router->router_name);

            $this->lastMessageType = 'success';
            $this->lastMessage = sprintf(
                'Import OK — Created: %d · Updated: %d · Skipped: %d · Users: %s',
                $result['created'],
                $result['updated'],
                $result['skipped'],
                implode(', ', array_slice($names, 0, 5)).(count($names) > 5 ? '…' : '')
            );

            if ($result['errors'] !== []) {
                $this->lastMessage .= ' · Errors: '.implode(' | ', array_slice($result['errors'], 0, 3));
                $this->lastMessageType = 'warning';

                return;
            }

            if (count($names) === 1) {
                $customer = $this->findCustomerByPppUsername($router, $names[0]);
                if ($customer) {
                    flash()->success(__('Customer imported: :name (:id)', [
                        'name' => $customer->customer_name,
                        'id' => $customer->customer_unique_id,
                    ]));

                    return redirect()->route('customers.show', encrypt($customer->customer_unique_id));
                }
            }
        } catch (\Throwable $e) {
            $this->lastMessageType = 'danger';
            $this->lastMessage = 'Import failed: '.$e->getMessage();
        }
    }

    public function importOne(string $username): void
    {
        $this->importNames([$username]);
    }

    public function importAllNames(array $names = []): void
    {
        flash()->warning(__('Bulk import disabled. Select specific users only.'));
    }

    protected function findCustomerByPppUsername(RouterList $router, string $username): ?\App\Models\CustomersInfo
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return null;
        }

        return \App\Models\CustomersInfo::query()
            ->whereHas('pppUser', function ($q) use ($router, $username) {
                $q->where('router_name', $router->router_name)
                    ->whereRaw('LOWER(username) = ?', [$username]);
            })
            ->first();
    }

    public function render()
    {
        return view('livewire.mikrotik-import-users', [
            'secretList' => $this->secretList,
        ])->layout('layouts.app');
    }
}
