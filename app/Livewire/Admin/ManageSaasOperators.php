<?php

namespace App\Livewire\Admin;

use App\Models\SaasInvoice;
use App\Models\SaasOperator;
use App\Models\SaasPlan;
use App\Rules\ValidPhoneDigits;
use App\Services\Saas\OperatorProvisioningService;
use App\Services\Saas\SaasBillingService;
use App\Services\Saas\SaasDomain;
use App\Services\Saas\SaasPlanCatalog;
use App\Services\Saas\SaasQuotaService;
use App\Services\Saas\StaffCashService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ManageSaasOperators extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public bool $showPlans = false;

    public ?int $viewingId = null;

    public ?int $editingOperatorId = null;

    public string $company = '';

    public string $contact_name = '';

    public string $email = '';

    public string $phone = '';

    public string $domain = '';

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

    public ?int $editingPlanId = null;

    public string $plan_name = '';

    public string $plan_slug = '';

    public int $plan_monthly = 0;

    public int $plan_yearly = 0;

    public int $plan_per_user = 0;

    public int $plan_max_customers = 0;

    public int $plan_max_olts = 0;

    public int $plan_max_routers = 0;

    public int $plan_max_staff = 0;

    public bool $plan_lifetime = false;

    public bool $plan_active = true;

    public string $apply_plan = '';

    public string $apply_cycle = 'monthly';

    public function mount(): void
    {
        if (! canSellSaas()) {
            abort(403, 'Only the platform owner can sell ISP admin access.');
        }

        app(OperatorProvisioningService::class)->ensureRoles();
        app(SaasPlanCatalog::class)->seed();
    }

    public function updatedPlan($value): void
    {
        $selected = SaasPlan::query()->where('slug', $value)->first();
        if ($selected?->isLifetime()) {
            $this->billing_cycle = 'lifetime';
        } elseif ($this->billing_cycle === 'lifetime') {
            $this->billing_cycle = 'monthly';
        }
    }

    public function openForm(): void
    {
        $this->resetValidation();
        $this->viewingId = null;
        $this->editingOperatorId = null;
        $this->showPlans = false;
        $this->showForm = true;
        $this->password = '';
        $this->password_confirmation = '';
    }

    public function openEdit(int $id): void
    {
        $op = SaasOperator::query()->with('user')->findOrFail($id);
        $this->resetValidation();
        $this->editingOperatorId = $op->id;
        $this->viewingId = $op->id;
        $this->showForm = true;
        $this->showPlans = false;
        $this->company = (string) $op->company;
        $this->contact_name = (string) $op->contact_name;
        $this->email = (string) $op->email;
        $this->phone = (string) ($op->phone ?? '');
        $this->notes = (string) ($op->notes ?? '');
        $this->password = '';
        $this->password_confirmation = '';
        $this->apply_plan = (string) $op->plan;
        $this->apply_cycle = $op->isLifetime() ? 'lifetime' : (string) $op->billing_cycle;
        $this->max_customers = (int) $op->max_customers;
        $this->max_olts = (int) $op->max_olts;
        $this->max_routers = (int) $op->max_routers;
        $this->max_staff = (int) $op->max_staff;
        $this->domain = (string) ($op->domain ?? '');
    }

    public function openPlans(): void
    {
        $this->resetValidation();
        $this->viewingId = null;
        $this->showForm = false;
        $this->showPlans = true;
        $this->resetPlanForm();
    }

    public function cancel(): void
    {
        $this->reset([
            'company', 'contact_name', 'email', 'phone', 'domain', 'plan', 'billing_cycle',
            'password', 'password_confirmation', 'notes', 'showForm', 'showPlans',
            'viewingId', 'editingOperatorId', 'payNote', 'apply_plan', 'apply_cycle',
        ]);
        $this->plan = 'standard';
        $this->billing_cycle = 'monthly';
        $this->resetPlanForm();
    }

    public function view(int $id): void
    {
        $op = SaasOperator::findOrFail($id);
        $this->viewingId = $op->id;
        $this->showForm = false;
        $this->showPlans = false;
        $this->max_customers = (int) $op->max_customers;
        $this->max_olts = (int) $op->max_olts;
        $this->max_routers = (int) $op->max_routers;
        $this->max_staff = (int) $op->max_staff;
        $this->payNote = '';
        $this->apply_plan = (string) $op->plan;
        $this->apply_cycle = $op->isLifetime() ? 'lifetime' : (string) $op->billing_cycle;
        $this->domain = (string) ($op->domain ?? '');
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
            'billing_cycle' => 'required|in:monthly,yearly,lifetime',
            'password' => 'required|string|min:8|confirmed',
            'notes' => 'nullable|string|max:500',
            'domain' => ['nullable', 'string', 'max:190', function ($attr, $value, $fail) {
                $this->assertDomain($value, $fail);
            }],
        ]);

        app(OperatorProvisioningService::class)->sell([
            'company' => $this->company,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'domain' => SaasDomain::normalize($this->domain),
            'plan' => $this->plan,
            'billing_cycle' => $this->billing_cycle,
            'password' => $this->password,
            'notes' => $this->notes ?: null,
        ]);

        if ($this->billing_cycle === 'lifetime') {
            flash()->success(__('Separate ISP opened. Lifetime free — they manage their own billing software, not Anetbd staff.'));
        } else {
            flash()->success(__('Separate ISP opened with a SaaS bill. They are not Anetbd staff. Unpaid due dates lock only their login.'));
        }
        $this->cancel();
    }

    public function updateOperator(): void
    {
        if (! canSellSaas()) {
            abort(403);
        }

        $operator = SaasOperator::query()->with('user')->findOrFail($this->editingOperatorId);
        $userId = $operator->user_id;

        $this->validate([
            'company' => 'required|string|max:160',
            'contact_name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email,'.$userId,
            'phone' => ['nullable', 'string', new ValidPhoneDigits],
            'password' => 'nullable|string|min:8|confirmed',
            'notes' => 'nullable|string|max:500',
        ]);

        app(OperatorProvisioningService::class)->updateProfile($operator, [
            'company' => $this->company,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'notes' => $this->notes ?: null,
            'password' => $this->password ?: null,
        ]);

        flash()->success(__('ISP admin updated. Login email / password change applies immediately.'));
        $this->password = '';
        $this->password_confirmation = '';
        $this->openEdit($operator->id);
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
        if ($op->isLifetime()) {
            flash()->info(__('Lifetime free ISP — no invoice.'));
            $this->viewingId = $id;

            return;
        }

        app(SaasBillingService::class)->issueInvoice($op);
        flash()->success(__('Invoice created from current user base.'));
        $this->viewingId = $id;
    }

    public function grantLifetime(int $id): void
    {
        if (! canSellSaas()) {
            abort(403);
        }

        app(OperatorProvisioningService::class)->grantLifetime(SaasOperator::findOrFail($id));
        flash()->success(__('This ISP is now lifetime free. No due date, no lock for unpaid rent.'));
        $this->view($id);
    }

    public function applySelectedPlan(int $id): void
    {
        if (! canSellSaas()) {
            abort(403);
        }

        $this->validate([
            'apply_plan' => 'required|exists:saas_plans,slug',
            'apply_cycle' => 'required|in:monthly,yearly,lifetime',
        ]);

        $plan = SaasPlan::query()->where('slug', $this->apply_plan)->firstOrFail();
        app(OperatorProvisioningService::class)->applyPlan(SaasOperator::findOrFail($id), $plan, $this->apply_cycle);
        flash()->success(__('Plan updated for this ISP.'));
        $this->view($id);
    }

    public function saveDomain(int $id): void
    {
        if (! canSellSaas()) {
            abort(403);
        }

        $this->validate([
            'domain' => ['nullable', 'string', 'max:190', function ($attr, $value, $fail) use ($id) {
                $this->assertDomain($value, $fail, $id);
            }],
        ]);

        $normalized = SaasDomain::normalize($this->domain);
        SaasOperator::findOrFail($id)->update(['domain' => $normalized]);
        $this->domain = (string) $normalized;
        if ($normalized) {
            try {
                app(\App\Services\Saas\CaddyDomainSync::class)->sync();
            } catch (\Throwable $e) {
                // Caddy may be unreachable in tests / local.
            }
        }
        flash()->success($normalized
            ? __('Domain saved. Point an A record to :ip — HTTPS will start after DNS.', ['ip' => SaasDomain::serverIpHint()])
            : __('Domain removed from this ISP.'));
        $this->viewingId = $id;
    }

    private function assertDomain(mixed $value, callable $fail, ?int $ignoreId = null): void
    {
        if (! filled($value)) {
            return;
        }

        $normalized = SaasDomain::normalize(is_string($value) ? $value : null);
        if (! $normalized) {
            $fail(__('Enter a real domain like billing.radiantbd.com — no http://.'));

            return;
        }

        if (SaasDomain::isReserved($normalized)) {
            $fail(__('That domain is reserved for Anetbd.'));

            return;
        }

        $taken = SaasOperator::query()
            ->where('domain', $normalized)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($taken) {
            $fail(__('This domain is already used by another ISP.'));
        }
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

    public function editPlan(int $id): void
    {
        $plan = SaasPlan::findOrFail($id);
        $this->editingPlanId = $plan->id;
        $this->plan_name = (string) $plan->name;
        $this->plan_slug = (string) $plan->slug;
        $this->plan_monthly = (int) $plan->monthly_price;
        $this->plan_yearly = (int) $plan->yearly_price;
        $this->plan_per_user = (int) $plan->per_user_rate;
        $this->plan_max_customers = (int) $plan->max_customers;
        $this->plan_max_olts = (int) $plan->max_olts;
        $this->plan_max_routers = (int) $plan->max_routers;
        $this->plan_max_staff = (int) $plan->max_staff;
        $this->plan_lifetime = (bool) $plan->is_lifetime;
        $this->plan_active = (bool) $plan->is_active;
        $this->showPlans = true;
        $this->showForm = false;
        $this->viewingId = null;
    }

    public function savePlan(): void
    {
        if (! canSellSaas()) {
            abort(403);
        }

        $this->validate([
            'plan_name' => 'required|string|max:80',
            'plan_monthly' => 'integer|min:0',
            'plan_yearly' => 'integer|min:0',
            'plan_per_user' => 'integer|min:0',
            'plan_max_customers' => 'integer|min:0',
            'plan_max_olts' => 'integer|min:0',
            'plan_max_routers' => 'integer|min:0',
            'plan_max_staff' => 'integer|min:0',
        ]);

        $slug = $this->editingPlanId
            ? SaasPlan::findOrFail($this->editingPlanId)->slug
            : Str::slug($this->plan_name ?: $this->plan_slug);

        if (! $this->editingPlanId && SaasPlan::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $payload = [
            'name' => $this->plan_name,
            'slug' => $slug,
            'monthly_price' => $this->plan_lifetime ? 0 : $this->plan_monthly,
            'yearly_price' => $this->plan_lifetime ? 0 : $this->plan_yearly,
            'per_user_rate' => $this->plan_lifetime ? 0 : $this->plan_per_user,
            'max_customers' => $this->plan_max_customers,
            'max_olts' => $this->plan_max_olts,
            'max_onus' => $this->plan_max_customers,
            'max_routers' => $this->plan_max_routers,
            'max_staff' => $this->plan_max_staff,
            'is_lifetime' => $this->plan_lifetime,
            'is_active' => $this->plan_active,
        ];

        if ($this->editingPlanId) {
            unset($payload['slug']);
            SaasPlan::findOrFail($this->editingPlanId)->update($payload);
            flash()->success(__('Package updated. Existing ISPs keep their current deal until you apply the plan.'));
        } else {
            $payload['modules'] = ['*'];
            $payload['sort_order'] = ((int) SaasPlan::query()->max('sort_order')) + 10;
            SaasPlan::query()->create($payload);
            flash()->success(__('New rent package created.'));
        }

        $this->resetPlanForm();
        $this->showPlans = true;
    }

    private function resetPlanForm(): void
    {
        $this->editingPlanId = null;
        $this->plan_name = '';
        $this->plan_slug = '';
        $this->plan_monthly = 0;
        $this->plan_yearly = 0;
        $this->plan_per_user = 0;
        $this->plan_max_customers = 0;
        $this->plan_max_olts = 0;
        $this->plan_max_routers = 0;
        $this->plan_max_staff = 0;
        $this->plan_lifetime = false;
        $this->plan_active = true;
    }

    public function render()
    {
        $detail = $this->viewingId
            ? SaasOperator::query()->with(['user', 'planCatalog', 'invoices' => fn ($q) => $q->latest()])->find($this->viewingId)
            : null;

        return view('livewire.admin.manage-saas-operators', [
            'operators' => SaasOperator::query()->with('user')->latest()->paginate(12),
            'plans' => SaasPlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'allPlans' => SaasPlan::query()->orderBy('sort_order')->get(),
            'detail' => $detail,
            'usage' => $detail ? app(SaasQuotaService::class)->snapshot($detail) : [],
            'staffRows' => $detail ? app(StaffCashService::class)->ledger($detail) : [],
            'serverIp' => SaasDomain::serverIpHint(),
        ])->layout('layouts.app');
    }
}
