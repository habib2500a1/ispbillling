<?php

namespace App\Services\Automation;

use App\Models\AutomaticProcess;
use App\Models\AutomaticProcessRun;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

final class AutomaticProcessFailureNotifier
{
    public function notify(AutomaticProcess $process, AutomaticProcessRun $run): void
    {
        if (! config('automation.notify_on_failure', true)) {
            return;
        }

        $cacheKey = 'auto-process-fail-notify:'.$process->id.':'.now()->toDateString();
        if (Cache::has($cacheKey)) {
            return;
        }

        $title = 'Automatic process failed';
        $body = $process->name.' — '.mb_substr((string) ($run->output ?? 'No output'), 0, 240);

        $users = User::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['super-admin', 'isp-admin', 'admin']))
                    ->orWhereHas('permissions', fn ($p) => $p->where('name', 'system.automations'));
            })
            ->get();

        foreach ($users as $user) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->danger()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('Open automations')
                        ->url(route('filament.admin.resources.automatic-processes.index')),
                ])
                ->sendToDatabase($user);
        }

        Cache::put($cacheKey, true, now()->endOfDay());
    }
}
