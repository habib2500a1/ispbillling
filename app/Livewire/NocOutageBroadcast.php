<?php

namespace App\Livewire;

use App\Models\NetworkOutageNotice;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NocOutageBroadcast extends Component
{
    public string $title = '';

    public string $message = '';

    public string $severity = 'warning';

    public string $scope = 'network';

    public string $area_label = '';

    public bool $is_active = true;

    public ?int $editingId = null;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['manage-tickets', 'view-tickets', 'olt-management', 'mikrotik-sync'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->message = '';
        $this->severity = 'warning';
        $this->scope = 'network';
        $this->area_label = '';
        $this->is_active = true;
    }

    public function edit(int $id): void
    {
        $notice = NetworkOutageNotice::findOrFail($id);
        $this->editingId = $notice->id;
        $this->title = (string) $notice->title;
        $this->message = (string) $notice->message;
        $this->severity = (string) $notice->severity;
        $this->scope = (string) $notice->scope;
        $this->area_label = (string) ($notice->area_label ?? '');
        $this->is_active = (bool) $notice->is_active;
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'severity' => 'required|in:info,warning,critical',
            'scope' => 'required|string|max:64',
            'area_label' => 'nullable|string|max:255',
        ]);

        $data = [
            'title' => trim($this->title),
            'message' => trim($this->message),
            'severity' => $this->severity,
            'scope' => $this->scope,
            'area_label' => trim($this->area_label) ?: null,
            'is_active' => $this->is_active,
            'starts_at' => now(),
            'created_by' => Auth::id(),
        ];

        if ($this->editingId) {
            NetworkOutageNotice::whereKey($this->editingId)->update($data);
            flash()->success(__('Outage notice updated.'));
        } else {
            NetworkOutageNotice::create($data);
            flash()->success(__('Outage notice published.'));
        }

        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $notice = NetworkOutageNotice::findOrFail($id);
        $notice->update(['is_active' => ! $notice->is_active]);
        flash()->success(__('Notice status updated.'));
    }

    public function delete(int $id): void
    {
        NetworkOutageNotice::whereKey($id)->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        flash()->success(__('Notice deleted.'));
    }

    public function render()
    {
        return view('livewire.noc-outage-broadcast', [
            'notices' => NetworkOutageNotice::query()->orderByDesc('id')->limit(50)->get(),
            'activeNotices' => NetworkOutageNotice::activeNow()->orderByDesc('id')->get(),
        ])->layout('layouts.app');
    }
}
