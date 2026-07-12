<?php

namespace App\Livewire;

use App\Services\Hr\HrHubService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HrHub extends Component
{
    public string $tab = 'roster';

    public ?int $leaveUserId = null;

    public string $leaveFrom = '';

    public string $leaveTo = '';

    public string $leaveType = 'casual';

    public string $leaveReason = '';

    public ?int $clockUserId = null;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['admin-users', 'admin-roles', 'admin.expenses'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->leaveUserId = Auth::id();
        $this->clockUserId = Auth::id();
        $this->leaveFrom = now()->toDateString();
        $this->leaveTo = now()->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function clockIn(): void
    {
        try {
            $uid = (int) ($this->clockUserId ?: Auth::id());
            app(HrHubService::class)->clockIn($uid);
            flash()->success(__('Clocked in.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function clockOut(): void
    {
        try {
            $uid = (int) ($this->clockUserId ?: Auth::id());
            app(HrHubService::class)->clockOut($uid);
            flash()->success(__('Clocked out.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function submitLeave(): void
    {
        $this->validate([
            'leaveUserId' => 'required|integer',
            'leaveFrom' => 'required|date',
            'leaveTo' => 'required|date|after_or_equal:leaveFrom',
            'leaveType' => 'required|string',
            'leaveReason' => 'nullable|string|max:2000',
        ]);

        try {
            app(HrHubService::class)->requestLeave([
                'user_id' => (int) $this->leaveUserId,
                'from_date' => $this->leaveFrom,
                'to_date' => $this->leaveTo,
                'leave_type' => $this->leaveType,
                'reason' => $this->leaveReason,
            ]);
            flash()->success(__('Leave request submitted.'));
            $this->leaveReason = '';
            $this->tab = 'leaves';
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function approveLeave(int $id): void
    {
        try {
            app(HrHubService::class)->reviewLeave($id, 'approved');
            flash()->success(__('Leave approved.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function rejectLeave(int $id): void
    {
        try {
            app(HrHubService::class)->reviewLeave($id, 'rejected');
            flash()->success(__('Leave rejected.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function refresh(): void
    {
        flash()->success(__('HR hub refreshed.'));
    }

    public function render()
    {
        $data = app(HrHubService::class)->payload();

        return view('livewire.hr-hub', $data)->layout('layouts.app');
    }
}
