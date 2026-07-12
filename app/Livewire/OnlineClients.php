<?php

namespace App\Livewire;

use App\Models\PPPSecrets;
use App\Models\RouterList;
use Livewire\Component;
use Livewire\WithPagination;

class OnlineClients extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'online';

    public string $routerFilter = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup']) && ! hasAccess(['Super Admin'], ['all-customer'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->pollOnlineQuiet();
    }

    public function pollOnlineQuiet(): void
    {
        $sync = app(MikrotikSync::class);
        $q = RouterList::query()->where('action', 'connected');
        if ($this->routerFilter !== '') {
            $q->where('router_name', $this->routerFilter);
        }
        foreach ($q->get() as $router) {
            try {
                $sync->refreshOnlineSessions($router->router_name);
            } catch (\Throwable) {
                // keep previous flags
            }
        }
    }

    public function refreshOnline(): void
    {
        $this->pollOnlineQuiet();
        flash()->success(__('Online status refreshed from MikroTik.'));
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = PPPSecrets::query()
            ->with(['customer'])
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
                    ->orWhere('router_name', 'like', $s);
            });
        }

        $onlineCount = PPPSecrets::where('status', '!=', 'removed')->whereNotNull('uptime')->count();
        $offlineCount = PPPSecrets::where('status', '!=', 'removed')->whereNull('uptime')->count();

        return view('livewire.online-clients', [
            'rows' => $query->paginate(50),
            'routers' => RouterList::orderBy('router_name')->pluck('router_name'),
            'onlineCount' => $onlineCount,
            'offlineCount' => $offlineCount,
        ])->layout('layouts.app');
    }
}
