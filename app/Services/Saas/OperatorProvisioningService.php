<?php

namespace App\Services\Saas;

use App\Models\SaasOperator;
use App\Models\SaasPlan;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Platform owner sells an ISP admin login. Buyer gets plan quotas, never resell.
 */
final class OperatorProvisioningService
{
    public const OPERATOR_ROLE = 'Operator';

    public const SELL_PERMISSION = 'saas-sell';

    public function __construct(
        private readonly SaasPlanCatalog $catalog,
        private readonly SaasBillingService $billing,
    ) {}

    public function ensureRoles(): Role
    {
        Permission::findOrCreate(self::SELL_PERMISSION, 'web');
        Permission::findOrCreate('staff-cash', 'web');

        $role = Role::findOrCreate(self::OPERATOR_ROLE, 'web');
        $all = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', self::SELL_PERMISSION)
            ->pluck('name')
            ->all();
        $role->syncPermissions($all);

        return $role;
    }

    /**
     * @param  array{
     *   company: string,
     *   contact_name: string,
     *   email: string,
     *   phone?: string|null,
     *   plan?: string,
     *   billing_cycle?: string,
     *   password: string,
     *   notes?: string|null,
     *   base_amount?: int|null,
     *   per_user_rate?: int|null,
     *   max_customers?: int|null,
     *   max_olts?: int|null,
     *   max_onus?: int|null,
     *   max_routers?: int|null,
     *   max_staff?: int|null,
     *   modules?: list<string>|null
     * }  $data
     */
    public function sell(array $data): SaasOperator
    {
        $this->ensureRoles();
        $this->catalog->seed();

        $slug = $data['plan'] ?? 'standard';
        $plan = SaasPlan::query()->where('slug', $slug)->first() ?? $this->catalog->resolve('standard');
        $cycle = ($data['billing_cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        $user = User::create([
            'name' => $data['contact_name'],
            'email' => $data['email'],
            'mobile' => $data['phone'] ?? null,
            'password' => bcrypt($data['password']),
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([self::OPERATOR_ROLE]);

        $operator = SaasOperator::create([
            'user_id' => $user->id,
            'saas_plan_id' => $plan->id,
            'company' => $data['company'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'plan' => $plan->slug,
            'billing_cycle' => $cycle,
            'base_amount' => (int) ($data['base_amount'] ?? $plan->priceFor($cycle)),
            'per_user_rate' => (int) ($data['per_user_rate'] ?? $plan->per_user_rate),
            'user_base_count' => 0,
            'amount' => 0,
            'status' => 'active',
            'can_resell' => false,
            'notes' => $data['notes'] ?? null,
            'sold_at' => now(),
            'next_due_at' => $cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            'max_customers' => (int) ($data['max_customers'] ?? $plan->max_customers),
            'max_olts' => (int) ($data['max_olts'] ?? $plan->max_olts),
            'max_onus' => (int) ($data['max_onus'] ?? $plan->max_onus),
            'max_routers' => (int) ($data['max_routers'] ?? $plan->max_routers),
            'max_staff' => (int) ($data['max_staff'] ?? $plan->max_staff),
            'modules' => $data['modules'] ?? $plan->modules,
        ]);

        $this->billing->quote($operator);
        $this->billing->issueInvoice($operator, $operator->next_due_at);

        return $operator->fresh(['user', 'planCatalog', 'invoices']);
    }

    public function setStatus(SaasOperator $operator, string $status): void
    {
        if ($status === 'active') {
            $this->billing->unlock($operator);

            return;
        }

        if ($status === 'locked') {
            $this->billing->lock($operator, 'manual');

            return;
        }

        $this->billing->suspend($operator);
    }

    public function applyPlan(SaasOperator $operator, SaasPlan $plan, string $cycle): void
    {
        $operator->update([
            'saas_plan_id' => $plan->id,
            'plan' => $plan->slug,
            'billing_cycle' => $cycle === 'yearly' ? 'yearly' : 'monthly',
            'base_amount' => $plan->priceFor($cycle === 'yearly' ? 'yearly' : 'monthly'),
            'per_user_rate' => $plan->per_user_rate,
            'max_customers' => $plan->max_customers,
            'max_olts' => $plan->max_olts,
            'max_onus' => $plan->max_onus,
            'max_routers' => $plan->max_routers,
            'max_staff' => $plan->max_staff,
            'modules' => $plan->modules,
        ]);
        $this->billing->quote($operator->fresh());
    }
}
