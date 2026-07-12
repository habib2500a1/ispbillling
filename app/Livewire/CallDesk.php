<?php

namespace App\Livewire;

use App\Services\CallCenter\CallDeskService;
use Livewire\Component;

class CallDesk extends Component
{
    public string $search = '';

    public ?string $selectedUid = null;

    public string $phone = '';

    public string $direction = 'outbound';

    public string $outcome = 'answered';

    public string $duration = '0';

    public string $remarks = '';

    public bool $createTicket = false;

    public string $activeQueue = 'call_queue';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    /** @var array<string, mixed>|null */
    public ?array $context = null;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['manage-tickets', 'view-tickets', 'payment-collection'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function updatedSearch(): void
    {
        $this->searchResults = app(CallDeskService::class)->searchCustomers($this->search, 12);
    }

    public function selectCustomer(string $uid): void
    {
        $this->selectedUid = $uid;
        $this->context = app(CallDeskService::class)->customerContext($uid);
        $this->phone = (string) ($this->context['customer']['mobile'] ?? '');
        $this->searchResults = [];
        $this->search = $this->context['customer']['customer_name'] ?? $uid;
        $this->resetLogForm(keepPhone: true);
    }

    public function selectFromQueue(string $uid): void
    {
        $this->selectCustomer($uid);
        $this->activeQueue = 'context';
    }

    public function setQueue(string $key): void
    {
        $this->activeQueue = $key;
    }

    public function logCall(): void
    {
        if (! $this->selectedUid) {
            flash()->warning(__('Select a customer first.'));

            return;
        }

        $this->validate([
            'outcome' => 'required|string',
            'direction' => 'required|in:inbound,outbound',
            'duration' => 'nullable|integer|min:0|max:86400',
            'remarks' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:40',
        ]);

        app(CallDeskService::class)->logCall($this->selectedUid, [
            'phone' => $this->phone,
            'direction' => $this->direction,
            'outcome' => $this->outcome,
            'duration_seconds' => (int) $this->duration,
            'remarks' => $this->remarks,
            'create_ticket' => $this->createTicket,
        ]);

        flash()->success(__('Call logged.'));
        $this->context = app(CallDeskService::class)->customerContext($this->selectedUid);
        $this->resetLogForm(keepPhone: true);
    }

    public function refresh(): void
    {
        if ($this->selectedUid) {
            $this->context = app(CallDeskService::class)->customerContext($this->selectedUid);
        }
        flash()->success(__('Call desk refreshed.'));
    }

    private function resetLogForm(bool $keepPhone = false): void
    {
        $phone = $keepPhone ? $this->phone : '';
        $this->direction = 'outbound';
        $this->outcome = 'answered';
        $this->duration = '0';
        $this->remarks = '';
        $this->createTicket = false;
        $this->phone = $phone;
    }

    public function render()
    {
        $payload = app(CallDeskService::class)->payload(25);

        return view('livewire.call-desk', [
            'stats' => $payload['stats'],
            'openTickets' => $payload['open_tickets'],
            'callQueue' => $payload['call_queue'],
            'recentCalls' => $payload['recent_calls'],
            'callbacks' => $payload['callbacks'],
            'outcomes' => $payload['outcomes'],
            'updatedAt' => $payload['updated_at'],
        ])->layout('layouts.app');
    }
}
