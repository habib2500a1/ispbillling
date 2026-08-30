<?php

namespace App\Livewire;

use App\Models\AutomaticProcess;
use App\Models\MainSiteData;
use App\Services\Automation\AutomaticProcessScheduler;
use Database\Seeders\AutomaticProcessSeeder;
use Livewire\Component;

class AutomaticProcesses extends Component
{
    public ?int $selectedProcessId = null;

    public bool $showRuns = false;

    public bool $showEdit = false;

    public ?int $editId = null;

    public string $edit_slug = '';

    public string $edit_name = '';

    public string $edit_description = '';

    public string $edit_interval = 'daily';

    public string $edit_execute_at = '23:45';

    public bool $edit_enabled = true;

    public int $disable_check_days = 0;

    public int $disable_check_no = 0;

    public string $expired_profile_name = 'Expired';

    public int $monthly_bill_sms_day = 1;

    public int $payment_reminder_days = 2;

    public float $late_fee_per_day = 10;

    public int $late_fee_grace_days = 0;

    public int $log_retention_days = 30;

    public string $bill_generate_at = '23:45';

    public bool $bill_generate_on = true;

    public string $sms_send_at = '10:00';

    public bool $sms_send_on = true;

    public string $disable_at = '08:30';

    public bool $disable_on = true;

    public string $reminder_at = '08:00';

    public bool $reminder_on = true;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['automatic-processes', 'site-settings'])) {
            abort(403, 'Unauthorized action.');
        }

        app(AutomaticProcessSeeder::class)->syncOnDeploy();
        $this->loadRules();
    }

    public function loadRules(): void
    {
        $this->disable_check_days = (int) (MainSiteData::getValue('disable_check_days', 0) ?: 0);
        $this->disable_check_no = (int) (MainSiteData::getValue('disable_check_no', 0) ?: 0);
        $this->expired_profile_name = (string) (MainSiteData::getValue('expired_profile_name', 'Expired') ?: 'Expired');
        $this->monthly_bill_sms_day = max(1, min(28, (int) (MainSiteData::getValue('monthly_bill_sms_day', 1) ?: 1)));
        $this->payment_reminder_days = max(0, (int) (MainSiteData::getValue('payment_reminder_days', 2) ?? 2));
        $this->late_fee_per_day = (float) (MainSiteData::getValue('late_fee_per_day', 10) ?: 0);
        $this->late_fee_grace_days = max(0, (int) (MainSiteData::getValue('late_fee_grace_days', 0) ?: 0));
        $this->log_retention_days = max(1, (int) (MainSiteData::getValue('log_retention_days', 30) ?: 30));

        $this->bill_generate_at = $this->clockOf('generate-monthly-bills', '23:45');
        $this->bill_generate_on = $this->enabledOf('generate-monthly-bills');
        $this->sms_send_at = $this->clockOf('monthly-bill-sms', '10:00');
        $this->sms_send_on = $this->enabledOf('monthly-bill-sms');
        $this->disable_at = $this->clockOf('disable-unpaid-users', '08:30');
        $this->disable_on = $this->enabledOf('disable-unpaid-users');
        $this->reminder_at = $this->clockOf('payment-reminder-alerts', '08:00');
        $this->reminder_on = $this->enabledOf('payment-reminder-alerts');
    }

    public function saveRules(): void
    {
        $this->validate([
            'bill_generate_at' => 'required|regex:/^\d{1,2}:\d{2}(:\d{2})?$/',
            'sms_send_at' => 'required|regex:/^\d{1,2}:\d{2}(:\d{2})?$/',
            'disable_at' => 'required|regex:/^\d{1,2}:\d{2}(:\d{2})?$/',
            'reminder_at' => 'required|regex:/^\d{1,2}:\d{2}(:\d{2})?$/',
            'disable_check_days' => 'integer|min:0|max:90',
            'disable_check_no' => 'integer|min:0',
            'expired_profile_name' => 'required|string|max:80',
            'monthly_bill_sms_day' => 'integer|min:1|max:28',
            'payment_reminder_days' => 'integer|min:0|max:30',
            'late_fee_per_day' => 'numeric|min:0|max:99999',
            'late_fee_grace_days' => 'integer|min:0|max:90',
            'log_retention_days' => 'integer|min:1|max:3650',
        ]);

        MainSiteData::setValue('disable_check_days', $this->disable_check_days);
        MainSiteData::setValue('disable_check_no', $this->disable_check_no);
        MainSiteData::setValue('expired_profile_name', $this->expired_profile_name);
        MainSiteData::setValue('monthly_bill_sms_day', $this->monthly_bill_sms_day);
        MainSiteData::setValue('payment_reminder_days', $this->payment_reminder_days);
        MainSiteData::setValue('late_fee_per_day', $this->late_fee_per_day);
        MainSiteData::setValue('late_fee_grace_days', $this->late_fee_grace_days);
        MainSiteData::setValue('log_retention_days', $this->log_retention_days);

        $this->applyJobClock('generate-monthly-bills', $this->bill_generate_at, $this->bill_generate_on);
        $this->applyJobClock('monthly-bill-sms', $this->sms_send_at, $this->sms_send_on);
        $this->applyJobClock('disable-unpaid-users', $this->disable_at, $this->disable_on);
        $this->applyJobClock('payment-reminder-alerts', $this->reminder_at, $this->reminder_on);

        $this->loadRules();

        flash()->success(__('Billing automation saved. Next runs use the new clocks and rules.'));
    }

    private function clockOf(string $slug, string $fallback): string
    {
        $at = (string) (AutomaticProcess::query()->where('slug', $slug)->value('execute_at') ?: $fallback);

        return preg_match('/^(\d{1,2}:\d{2})/', $at, $m) ? $m[1] : $fallback;
    }

    private function enabledOf(string $slug): bool
    {
        $row = AutomaticProcess::query()->where('slug', $slug)->first();

        return $row ? (bool) $row->enabled : true;
    }

    private function applyJobClock(string $slug, string $clock, bool $enabled): void
    {
        $process = AutomaticProcess::query()->where('slug', $slug)->first();
        if (! $process) {
            return;
        }

        $normalized = preg_match('/^(\d{1,2}:\d{2})/', $clock, $m) ? $m[1] : '00:00';
        $process->update([
            'execute_at' => $normalized,
            'interval' => 'daily',
            'enabled' => $enabled,
        ]);
        $process->forceFill([
            'next_run_at' => app(AutomaticProcessScheduler::class)->computeNextRunAt($process->fresh()),
        ])->save();
    }

    public function toggleEnabled(int $id): void
    {
        $process = AutomaticProcess::query()->findOrFail($id);
        $process->update(['enabled' => ! $process->enabled]);
        if ($process->enabled) {
            $process->forceFill([
                'next_run_at' => app(AutomaticProcessScheduler::class)->computeNextRunAt($process),
            ])->save();
        }
        flash()->success($process->enabled ? __('Process enabled.') : __('Process disabled.'));
    }

    public function runNow(int $id): void
    {
        $process = AutomaticProcess::query()->findOrFail($id);
        $ok = app(AutomaticProcessScheduler::class)->run($process, force: true, triggeredBy: 'manual');
        flash()->{$ok ? 'success' : 'error'}($ok
            ? "'{$process->name}' ran successfully."
            : "'{$process->name}' failed — check run history.");
    }

    public function openEdit(int $id): void
    {
        $process = AutomaticProcess::query()->findOrFail($id);
        $this->editId = $process->id;
        $this->edit_slug = (string) $process->slug;
        $this->edit_name = (string) $process->name;
        $this->edit_description = (string) ($process->description ?? '');
        $this->edit_interval = (string) $process->interval;
        $at = (string) ($process->execute_at ?: '00:00');
        $this->edit_execute_at = preg_match('/^(\d{1,2}:\d{2})/', $at, $m) ? $m[1] : '00:00';
        $this->edit_enabled = (bool) $process->enabled;
        $this->showEdit = true;
        $this->showRuns = false;
    }

    public function saveProcess(): void
    {
        $this->validate([
            'edit_name' => 'required|string|max:120',
            'edit_description' => 'nullable|string|max:400',
            'edit_interval' => 'required|in:'.implode(',', array_keys(AutomaticProcess::INTERVALS)),
            'edit_execute_at' => 'required|regex:/^\d{1,2}:\d{2}(:\d{2})?$/',
        ]);

        $clock = preg_match('/^(\d{1,2}:\d{2})/', $this->edit_execute_at, $m) ? $m[1] : '00:00';

        $process = AutomaticProcess::query()->findOrFail($this->editId);
        $process->update([
            'name' => $this->edit_name,
            'description' => $this->edit_description,
            'interval' => $this->edit_interval,
            'execute_at' => $clock,
            'enabled' => $this->edit_enabled,
        ]);
        $process->forceFill([
            'next_run_at' => app(AutomaticProcessScheduler::class)->computeNextRunAt($process->fresh()),
        ])->save();

        $this->showEdit = false;
        flash()->success(__('Schedule saved. Next run: :when', [
            'when' => $process->fresh()->next_run_at?->format('d M Y H:i') ?: '—',
        ]));
    }

    public function syncDefaults(): void
    {
        $stats = (new AutomaticProcessSeeder)->syncOnDeploy();
        flash()->success(__('Missing jobs loaded (:created added). Your times were not overwritten.', [
            'created' => $stats['created'],
        ]));
    }

    public function viewRuns(int $id): void
    {
        $this->selectedProcessId = $id;
        $this->showRuns = true;
        $this->showEdit = false;
    }

    public function closeRuns(): void
    {
        $this->showRuns = false;
        $this->selectedProcessId = null;
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->editId = null;
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
        $billJob = $processes->firstWhere('slug', 'generate-monthly-bills');
        $smsJob = $processes->firstWhere('slug', 'monthly-bill-sms');
        $disableJob = $processes->firstWhere('slug', 'disable-unpaid-users');

        return view('livewire.automatic-processes', [
            'processes' => $processes,
            'selected' => $selected,
            'intervals' => AutomaticProcess::INTERVALS,
            'billGenerateAt' => $billJob?->execute_at ?: '23:45',
            'smsSendAt' => $smsJob?->execute_at ?: '10:00',
            'disableAt' => $disableJob?->execute_at ?: '08:30',
            'summary' => [
                'total' => $processes->count(),
                'enabled' => $enabled,
                'disabled' => $processes->count() - $enabled,
                'failed' => $processes->where('last_status', 'failed')->count(),
            ],
        ])->layout('layouts.app');
    }
}
