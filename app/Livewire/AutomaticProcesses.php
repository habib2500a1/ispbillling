<?php

namespace App\Livewire;

use App\Models\AutomaticProcess;
use App\Services\Automation\AutomaticProcessScheduler;
use Database\Seeders\AutomaticProcessSeeder;
use Livewire\Component;

class AutomaticProcesses extends Component
{
    public ?int $selectedProcessId = null;

    public bool $showRuns = false;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['automatic-processes', 'site-settings'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function toggleEnabled(int $id): void
    {
        $process = AutomaticProcess::query()->findOrFail($id);
        $process->update(['enabled' => ! $process->enabled]);
        flash()->success($process->enabled ? 'Process enabled.' : 'Process disabled.');
    }

    public function runNow(int $id): void
    {
        $process = AutomaticProcess::query()->findOrFail($id);
        $ok = app(AutomaticProcessScheduler::class)->run($process, force: true, triggeredBy: 'manual');
        flash()->{$ok ? 'success' : 'error'}($ok
            ? "'{$process->name}' ran successfully."
            : "'{$process->name}' failed — check run history.");
    }

    public function syncDefaults(): void
    {
        $stats = (new AutomaticProcessSeeder)->syncOnDeploy();
        flash()->success("Synced automatic processes ({$stats['created']} added, {$stats['updated']} updated).");
    }

    public function viewRuns(int $id): void
    {
        $this->selectedProcessId = $id;
        $this->showRuns = true;
    }

    public function closeRuns(): void
    {
        $this->showRuns = false;
        $this->selectedProcessId = null;
    }

    public function render()
    {
        $processes = AutomaticProcess::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $selected = $this->selectedProcessId
            ? AutomaticProcess::query()->with(['runs' => fn ($q) => $q->latest('id')->limit(15)])->find($this->selectedProcessId)
            : null;

        $enabled = $processes->where('enabled', true)->count();
        $failed = $processes->where('last_status', 'failed')->count();

        return view('livewire.automatic-processes', [
            'processes' => $processes,
            'selected' => $selected,
            'summary' => [
                'total' => $processes->count(),
                'enabled' => $enabled,
                'disabled' => $processes->count() - $enabled,
                'failed' => $failed,
            ],
        ])->layout('layouts.app');
    }
}
