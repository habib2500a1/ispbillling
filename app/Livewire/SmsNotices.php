<?php

namespace App\Livewire;

use App\Models\SmsTemplate;
use App\Services\Sms\SmsNoticesService;
use Livewire\Component;

class SmsNotices extends Component
{
    public int $dueSoonDays = 3;

    public string $activeSection = 'overdue';

    public string $message = '';

    public ?int $templateId = null;

    /** @var list<string> */
    public array $selected = [];

    /** @var list<array{name: string, mobile: string, url: string}> */
    public array $waLinks = [];

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['sms-setup', 'payment-collection', 'billing-notices'])) {
            abort(403, 'Unauthorized action.');
        }

        $days = (int) request()->query('days', 3);
        $this->dueSoonDays = max(1, min(14, $days));
    }

    public function updatedDueSoonDays(): void
    {
        $this->dueSoonDays = max(1, min(14, (int) $this->dueSoonDays));
        $this->selected = [];
        $this->waLinks = [];
    }

    public function setSection(string $key): void
    {
        $this->activeSection = $key;
        $this->selected = [];
        $this->waLinks = [];
    }

    public function updatedTemplateId(): void
    {
        if (! $this->templateId) {
            return;
        }

        $tpl = SmsTemplate::query()->find($this->templateId);
        if ($tpl) {
            $this->message = (string) $tpl->template;
        }
    }

    public function toggleUid(string $uid): void
    {
        if (in_array($uid, $this->selected, true)) {
            $this->selected = array_values(array_filter($this->selected, fn ($v) => $v !== $uid));
        } else {
            $this->selected[] = $uid;
        }
        $this->waLinks = [];
    }

    public function selectVisibleSection(): void
    {
        $payload = app(SmsNoticesService::class)->payload($this->dueSoonDays, 50);
        $uids = [];
        foreach ($payload['sections'] as $section) {
            if ($this->activeSection !== 'all' && ($section['key'] ?? '') !== $this->activeSection) {
                continue;
            }
            foreach ($section['items'] as $item) {
                if (! empty($item['customer_unique_id'])) {
                    $uids[] = (string) $item['customer_unique_id'];
                }
            }
        }
        $this->selected = array_values(array_unique($uids));
        $this->waLinks = [];
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->waLinks = [];
    }

    public function queueSms(): void
    {
        $this->validate([
            'message' => 'required|string|min:5|max:1000',
            'selected' => 'required|array|min:1',
        ]);

        try {
            $result = app(SmsNoticesService::class)->queueSms($this->selected, $this->message);
            flash()->success(__(
                'SMS queued for :n customer(s). Skipped no-mobile: :m, missing: :x',
                [
                    'n' => $result['queued'],
                    'm' => $result['skipped_no_mobile'],
                    'x' => $result['skipped_missing'],
                ]
            ));
            $this->waLinks = [];
        } catch (\InvalidArgumentException $e) {
            flash()->error($e->getMessage());
        } catch (\Throwable $e) {
            flash()->error(__('Failed to queue SMS: :msg', ['msg' => $e->getMessage()]));
        }
    }

    public function prepareWhatsApp(): void
    {
        if ($this->selected === []) {
            flash()->warning(__('Select at least one customer.'));

            return;
        }

        $this->waLinks = app(SmsNoticesService::class)->whatsappLinks($this->selected, $this->message);
        if ($this->waLinks === []) {
            flash()->warning(__('No WhatsApp-ready mobile numbers in selection.'));
        }
    }

    public function refresh(): void
    {
        flash()->success(__('SMS notices refreshed.'));
    }

    public function render()
    {
        $payload = app(SmsNoticesService::class)->payload($this->dueSoonDays, 50);

        $sections = $payload['sections'];
        $visible = $sections;
        if ($this->activeSection !== 'all') {
            $visible = array_values(array_filter(
                $sections,
                fn (array $s): bool => ($s['key'] ?? '') === $this->activeSection
            ));
        }

        return view('livewire.sms-notices', [
            'summary' => $payload['summary'],
            'sections' => $visible,
            'allSections' => $sections,
            'templates' => $payload['templates'],
            'gateway' => $payload['gateway'],
            'recentLogs' => $payload['recent_logs'],
            'placeholders' => $payload['placeholders'],
            'updatedAt' => $payload['updated_at'],
        ])->layout('layouts.app');
    }
}
