<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class OnlineClients extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'online';

    public string $routerFilter = '';

    public ?int $trafficId = null;

    public string $trafficUser = '';

    public string $trafficRouter = '';

    public string $trafficInterface = '';

    public float $rxSpeed = 0;

    public float $txSpeed = 0;

    public float $lastPollTime = 0;

    public float $lastResolveTime = 0;

    public ?int $onuId = null;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup']) && ! hasAccess(['Super Admin'], ['all-customer'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function pollOnlineQuiet(): void
    {
        $this->syncRouters(false);
    }

    public function refreshOnline(): void
    {
        $this->syncRouters(true);
        flash()->success(__('Online status refreshed from MikroTik.'));
    }

    public function refreshOne(int $id): void
    {
        $row = PPPSecrets::query()
            ->where('status', '!=', 'removed')
            ->find($id, ['id', 'username', 'router_name']);
        if (! $row?->router_name) {
            flash()->error(__('Router not found.'));

            return;
        }

        try {
            $online = app(MikrotikSync::class)->refreshOneSession($row->router_name, (string) $row->username);
            flash()->success($online
                ? __('Session is online.')
                : __('Session is offline.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    protected function syncRouters(bool $withDetails): void
    {
        $sync = app(MikrotikSync::class);
        $q = RouterList::query()->where('action', 'connected');
        if ($this->routerFilter !== '') {
            $q->where('router_name', $this->routerFilter);
        }
        foreach ($q->get() as $router) {
            try {
                $sync->refreshOnlineSessions($router->router_name, $withDetails);
            } catch (\Throwable) {
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRouterFilter(): void
    {
        $this->resetPage();
    }

    public function openTraffic(int $id): void
    {
        $row = PPPSecrets::query()
            ->where('status', '!=', 'removed')
            ->find($id, ['id', 'username', 'router_name', 'uptime']);
        if (! $row) {
            return;
        }
        if (empty($row->uptime) || ! $row->router_name) {
            flash()->warning(__('Client is offline — live traffic unavailable.'));

            return;
        }

        $this->trafficId = $row->id;
        $this->trafficUser = (string) $row->username;
        $this->trafficRouter = (string) $row->router_name;
        $this->trafficInterface = '<pppoe-'.$row->username.'>';
        $this->rxSpeed = 0;
        $this->txSpeed = 0;
        $this->lastPollTime = 0;
        $this->lastResolveTime = 0;
    }

    public function closeTraffic(): void
    {
        $this->trafficId = null;
        $this->trafficUser = '';
        $this->trafficRouter = '';
        $this->trafficInterface = '';
        $this->rxSpeed = 0;
        $this->txSpeed = 0;
    }

    public function pollTraffic(): void
    {
        if (! $this->trafficId || ! $this->trafficRouter || ! $this->trafficInterface) {
            return;
        }
        if (microtime(true) - $this->lastPollTime < 1.5) {
            return;
        }
        $this->lastPollTime = microtime(true);

        $row = PPPSecrets::query()->find($this->trafficId);
        if (! $row || empty($row->uptime)) {
            $this->rxSpeed = 0;
            $this->txSpeed = 0;

            return;
        }

        try {
            if ($this->rxSpeed == 0 && $this->txSpeed == 0 && (microtime(true) - $this->lastResolveTime > 8)) {
                $this->lastResolveTime = microtime(true);
                $resolved = $this->resolveInterfaceName($row->router_name, (string) $row->username);
                if ($resolved && $resolved !== $this->trafficInterface) {
                    $this->trafficInterface = $resolved;
                }
            }

            $data = app(MikrotikController::class)->getLiveTraffic($this->trafficRouter, $this->trafficInterface);
            if (isset($data['rx-bits-per-second']) || isset($data['tx-bits-per-second'])) {
                $this->rxSpeed = (float) ($data['rx-bits-per-second'] ?? 0);
                $this->txSpeed = (float) ($data['tx-bits-per-second'] ?? 0);
                $this->dispatch('oc-traffic-updated', rx: $this->rxSpeed, tx: $this->txSpeed);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function openOnu(int $id): void
    {
        $this->onuId = $id;
    }

    public function closeOnu(): void
    {
        $this->onuId = null;
    }

    public function syncOnu(int $id): void
    {
        $row = PPPSecrets::query()->with('customer')->find($id);
        if (! $row?->customer) {
            flash()->warning(__('No customer linked to this PPP user.'));

            return;
        }

        $result = app(\App\Services\Olt\LocalOltOnuSyncService::class)->syncForCustomer($row->customer);
        if ($result['ok']) {
            flash()->success($result['message']);
        } else {
            flash()->warning($result['message']);
        }
        $this->onuId = $id;
    }

    protected function resolveInterfaceName(string $routerName, string $username): ?string
    {
        if (! $routerName || ! $username) {
            return null;
        }

        try {
            $interfaces = app(MikrotikController::class)->getInterfaces($routerName);
            $lowerUser = strtolower($username);

            foreach ($interfaces as $iface) {
                $name = $iface['name'] ?? '';
                $lowerName = strtolower($name);
                if ($lowerName === "<pppoe-{$lowerUser}>"
                    || $lowerName === "pppoe-{$lowerUser}"
                    || $lowerName === $lowerUser
                ) {
                    return $name;
                }
            }
            foreach ($interfaces as $iface) {
                $name = $iface['name'] ?? '';
                if (str_contains(strtolower($name), $lowerUser)) {
                    return $name;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    public static function disconnectLabel(?string $reason): string
    {
        if (! $reason) {
            return '';
        }
        $map = [
            'user-request' => __('User logged out'),
            'lost-carrier' => __('Lost carrier / cable / ONU'),
            'lost carrier' => __('Lost carrier / cable / ONU'),
            'peer-not-responding' => __('Peer not responding'),
            'admin hangup' => __('Admin hangup'),
            'admin-reset' => __('Admin reset'),
            'hung-up' => __('Hung up'),
            'disabled' => __('Account disabled'),
            'radius timeout' => __('RADIUS timeout'),
            'no valid ip address' => __('No valid IP'),
            'ppp session dropped' => __('PPP session dropped'),
            'nas-request' => __('NAS request'),
            'port-error' => __('Port error'),
            'service-unavailable' => __('Service unavailable'),
        ];

        return $map[strtolower(trim($reason))] ?? $reason;
    }

    public static function sessionDuration(?string $uptime): string
    {
        if (! $uptime) {
            return '—';
        }
        try {
            $diff = Carbon::parse($uptime)->diff(now());
        } catch (\Throwable) {
            return '—';
        }

        return sprintf('%dd:%02dh:%02dm:%02ds', $diff->days, $diff->h, $diff->i, $diff->s);
    }

    public function render()
    {
        $query = PPPSecrets::query()
            ->with(['customer.onus', 'customer.official', 'customer.package'])
            ->where('status', '!=', 'removed')
            ->orderByRaw('CASE WHEN uptime IS NULL THEN 1 ELSE 0 END')
            ->orderBy('username');

        if ($this->routerFilter !== '') {
            $query->where('router_name', $this->routerFilter);
        }

        if ($this->filter === 'online') {
            $query->whereNotNull('uptime');
        } elseif ($this->filter === 'offline') {
            $query->whereNull('uptime');
        }

        if (trim($this->search) !== '') {
            $s = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($s) {
                $q->where('username', 'like', $s)
                    ->orWhere('profile', 'like', $s)
                    ->orWhere('comment', 'like', $s)
                    ->orWhere('router_name', 'like', $s)
                    ->orWhere('ppp_remote_ip', 'like', $s)
                    ->orWhere('caller_id', 'like', $s)
                    ->orWhere('last_caller_id', 'like', $s)
                    ->orWhere('last_disconnect_reason', 'like', $s)
                    ->orWhereHas('customer', function ($c) use ($s) {
                        $c->where('customer_name', 'like', $s)
                            ->orWhere('customer_unique_id', 'like', $s)
                            ->orWhere('mobile', 'like', $s)
                            ->orWhere('address', 'like', $s);
                    });
            });
        }

        $onlineCount = PPPSecrets::where('status', '!=', 'removed')->whereNotNull('uptime')->count();
        $offlineCount = PPPSecrets::where('status', '!=', 'removed')->whereNull('uptime')->count();

        return view('livewire.online-clients', [
            'rows' => $query->paginate(40),
            'routers' => RouterList::orderBy('router_name')->pluck('router_name'),
            'onlineCount' => $onlineCount,
            'offlineCount' => $offlineCount,
        ])->layout('layouts.app');
    }
}
