<?php

namespace App\Livewire\Admin;

use App\Http\Controllers\MikrotikController;
use App\Models\MikrotikLog;
use App\Models\RouterList;
use Livewire\Component;
use Livewire\WithPagination;

class PppoeAudit extends Component
{
    use WithPagination;

    public string $selectedRouter = '';

    public string $search = '';

    public string $statusFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->selectedRouter = $this->routerNames()->first() ?? '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedRouter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function refreshFromRouter(): void
    {
        $this->authorizeAccess();
        if (! $this->selectedRouter || ! $this->routerNames()->contains($this->selectedRouter)) {
            $this->addError('selectedRouter', __('Choose one of your configured routers.'));

            return;
        }

        try {
            $logs = app(MikrotikController::class)->getRouterLogs($this->selectedRouter, 200);
            $pppLogs = array_values(array_filter($logs, fn (array $log) => $this->isPppLog($log['topics'] ?? '', $log['message'] ?? '')));
            $stored = app(MikrotikController::class)->storeRouterLogs($this->selectedRouter, $pppLogs);
            flash()->success(__('Router checked. :count new PPP-related log entries saved.', ['count' => $stored]));
        } catch (\Throwable $exception) {
            report($exception);
            flash()->error(__('Could not read logs from this router.'));
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeAccess();
        $routers = $this->routerNames();
        $query = MikrotikLog::query()
            ->whereIn('router_name', $routers)
            ->where(function ($q) {
                $q->where('topics', 'like', '%ppp%')
                    ->orWhere('topics', 'like', '%radius%')
                    ->orWhere('topics', 'like', '%account%')
                    ->orWhere('message', 'like', '%pppoe%')
                    ->orWhere('message', 'like', '%ppp user%');
            })
            ->when($this->selectedRouter !== '', fn ($q) => $q->where('router_name', $this->selectedRouter))
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);
                $q->where(function ($inner) use ($term) {
                    $inner->where('message', 'like', '%'.$term.'%')
                        ->orWhere('topics', 'like', '%'.$term.'%');
                });
            })
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->statusFilter === 'failed', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('topics', 'like', '%error%')
                        ->orWhere('topics', 'like', '%warning%')
                        ->orWhere('message', 'like', '%fail%')
                        ->orWhere('message', 'like', '%invalid%')
                        ->orWhere('message', 'like', '%reject%')
                        ->orWhere('message', 'like', '%denied%');
                });
            })
            ->when($this->statusFilter === 'success', function ($q) {
                $q->where('message', 'not like', '%fail%')
                    ->where('message', 'not like', '%invalid%')
                    ->where('message', 'not like', '%reject%')
                    ->where('message', 'not like', '%denied%')
                    ->where('topics', 'not like', '%error%')
                    ->where('topics', 'not like', '%warning%');
            })
            ->latest();

        $logs = $query->paginate(30);
        $base = MikrotikLog::query()
            ->whereIn('router_name', $routers)
            ->where(function ($q) {
                $q->where('topics', 'like', '%ppp%')
                    ->orWhere('topics', 'like', '%radius%')
                    ->orWhere('topics', 'like', '%account%')
                    ->orWhere('message', 'like', '%pppoe%')
                    ->orWhere('message', 'like', '%ppp user%');
            });

        $summary = [
            'all' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'failed' => (clone $base)->where(function ($q) {
                $q->where('topics', 'like', '%error%')
                    ->orWhere('topics', 'like', '%warning%')
                    ->orWhere('message', 'like', '%fail%')
                    ->orWhere('message', 'like', '%invalid%')
                    ->orWhere('message', 'like', '%reject%')
                    ->orWhere('message', 'like', '%denied%');
            })->count(),
        ];

        return view('livewire.admin.pppoe-audit', compact('logs', 'routers', 'summary'))
            ->layout('layouts.app');
    }

    private function routerNames()
    {
        return RouterList::query()->where('action', 'connected')->orderBy('router_name')->pluck('router_name');
    }

    private function isPppLog(string $topics, string $message): bool
    {
        $content = strtolower($topics.' '.$message);

        return str_contains($content, 'ppp')
            || str_contains($content, 'pppoe')
            || str_contains($content, 'radius');
    }

    private function authorizeAccess(): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup', 'all-customer'])) {
            abort(403, 'Unauthorized action.');
        }
    }
}
