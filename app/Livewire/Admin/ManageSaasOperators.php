<?php

namespace App\Livewire\Admin;

use App\Models\SaasInvoice;
use App\Models\SaasOperator;
use App\Models\SaasPlan;
use App\Rules\ValidPhoneDigits;
use App\Services\Saas\OperatorProvisioningService;
use App\Services\Saas\SaasBillingService;
use App\Services\Saas\SaasPlanCatalog;
use App\Services\Saas\SaasQuotaService;
use App\Services\Saas\StaffCashService;
use Livewire\Component;
use Livewire\WithPagination;

class ManageSaasOperators extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $viewingId = null;

    public string $company = '';

    public string $contact_name = '';

    public string $email = '';

    public string $phone = '';

    public string $plan = 'standard';

    public string $billing_cycle = 'monthly';

    public string $password = '';

    public string $password_confirmation = '';

    public string $notes = '';

    public string $payNote = '';

    public int $max_customers = 0;

    public int $max_olts = 0;

    public int $max_routers = 0;

    public int $max_staff = 0;

    public function mount(): void
    {
        if (! canSellSaas()) {
            abort(403, 'Only the platform owner can sell ISP admin access.');
        }

        app(OperatorProvisioningService::class)->ensureRoles();
        app(SaasPlanCatalog::class)->seed();
    }

    public function openForm(): void
    {
        $this->resetValidation();
        $this->viewingId = null;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset([
            'company', 'contact_name', 'email', 'phone', 'plan', 'billing_cycle',
            'password', 'password_confirmation', 'notes', 'showForm', 'viewingId', 'payNote',
        ]);
        $this->plan = 'standard';
        $this->billing_cycle = 'monthly';
    }

    public function view(int $id): void
    {
        $op = SaasOperator::findOrFail($id);
        $this->viewingId = $op->id;
        $this->showForm = false;
        $this->max_customers = (int) $op->max_customers;
        $this->max_olts = (int) $op->max_olts;
        $this->max_routers = (int) $op->max_routers;
        $this->max_staff = (int) $op->max_staff;
        $this->payNote = '';
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
            'plan' => 'required|exists:saas_plans,slug',
            'billing_cycle' => 'required|in:monthly,yearly',
            'password' => 'required|string|min:8|confirmed',
            'notes' => 'nullable|string|max:500',
        ]);

        app(OperatorProvisioningService::class)->sell([
            'company' => $this->company,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'plan' => $this->plan,
            'billing_cycle' => $this->billing_cycle,
            'password' => $this->password,
            'notes' => $this->notes ?: null,
        ]);

        flash()->success(__('ISP admin opened with a SaaS bill. Unpaid due dates lock the account.'));
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
        flash()->success($next === 'active' ? __('Operator unlocked.') : __('Operator suspended.'));
    }

    public function lockNow(int $id): void
    {
        app(OperatorProvisioningService::class)->setStatus(SaasOperator::findOrFail($id), 'locked');
        flash()->success(__('Operator locked.'));
    }

    public function recordPayment(int $invoiceId): void
    {
        $invoice = SaasInvoice::findOrFail($invoiceId);
        app(SaasBillingService::class)->markPaid($invoice, $this->payNote ?: null);
        flash()->success(__('Payment recorded. Account unlocked until the next due date.'));
        $this->payNote = '';
        $this->viewingId = $invoice->saas_operator_id;
    }

    public function generateInvoice(int $id): void
    {
        $op = SaasOperator::findOrFail($id);
        app(SaasBillingService::class)->issueInvoice($op);
        flash()->success(__('Invoice created from current user base.'));
        $this->viewingId = $id;
    }

    public function saveQuotas(int $id): void
    {
        $this->validate([
            'max_customers' => 'integer|min:0',
            'max_olts' => 'integer|min:0',
            'max_routers' => 'integer|min:0',
            'max_staff' => 'integer|min:0',
        ]);

        SaasOperator::findOrFail($id)->update([
            'max_customers' => $this->max_customers,
            'max_olts' => $this->max_olts,
            'max_routers' => $this->max_routers,
            'max_staff' => $this->max_staff,
        ]);
        flash()->success(__('Limits saved. 0 means unlimited.'));
    }

    public function render()
    {
        $detail = $this->viewingId
            ? SaasOperator::query()->with(['user', 'planCatalog', 'invoices' => fn ($q) => $q->latest()])->find($this->viewingId)
            : null;

        return view('livewire.admin.manage-saas-operators', [
            'operators' => SaasOperator::query()->with('user')->latest()->paginate(12),
            'plans' => SaasPlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'detail' => $detail,
            'usage' => $detail ? app(SaasQuotaService::class)->snapshot($detail) : [],
            'staffRows' => $detail ? app(StaffCashService::class)->ledger($detail) : [],
        ])->layout('layouts.app');
    }
}
