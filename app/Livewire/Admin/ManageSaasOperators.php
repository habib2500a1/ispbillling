<?php

namespace App\Livewire\Admin;

use App\Models\SaasOperator;
use App\Rules\ValidPhoneDigits;
use App\Services\Saas\OperatorProvisioningService;
use Livewire\Component;
use Livewire\WithPagination;

class ManageSaasOperators extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $company = '';

    public string $contact_name = '';

    public string $email = '';

    public string $phone = '';

    public string $plan = 'standard';

    public string $password = '';

    public string $password_confirmation = '';

    public string $notes = '';

    public function mount(): void
    {
        if (! canSellSaas()) {
            abort(403, 'Only the platform owner can sell ISP admin access.');
        }

        app(OperatorProvisioningService::class)->ensureRoles();
    }

    public function openForm(): void
    {
        $this->resetValidation();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset(['company', 'contact_name', 'email', 'phone', 'plan', 'password', 'password_confirmation', 'notes']);
        $this->showForm = false;
    }

    public function sell(): void
    {
        if (! canSellSaas()) {
            abort(403);
        }

        $this->validate([
            'company' => 'required|string|max:160',
            'contact_name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => ['nullable', 'string', new ValidPhoneDigits],
            'plan' => 'required|in:standard,pro,enterprise',
            'password' => 'required|string|min:8|confirmed',
            'notes' => 'nullable|string|max:500',
        ]);

        app(OperatorProvisioningService::class)->sell([
            'company' => $this->company,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'plan' => $this->plan,
            'password' => $this->password,
            'notes' => $this->notes ?: null,
        ]);

        flash()->success(__('ISP admin opened. They can run the full billing desk but cannot resell.'));
        $this->cancel();
    }

    public function toggleStatus(int $id): void
    {
        if (! canSellSaas()) {
            abort(403);
        }

        $operator = SaasOperator::findOrFail($id);
        $next = $operator->status === 'active' ? 'suspended' : 'active';
        app(OperatorProvisioningService::class)->setStatus($operator, $next);
        flash()->success($next === 'active' ? __('Operator activated.') : __('Operator suspended.'));
    }

    public function render()
    {
        return view('livewire.admin.manage-saas-operators', [
            'operators' => SaasOperator::query()->with('user')->latest()->paginate(12),
        ])->layout('layouts.app');
    }
}
