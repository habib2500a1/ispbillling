<?php

namespace App\Services\Saas;

use App\Models\SaasOperator;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Platform owner sells an ISP admin login. Buyer gets full ops, never resell.
 */
final class OperatorProvisioningService
{
    public const OPERATOR_ROLE = 'Operator';

    public const SELL_PERMISSION = 'saas-sell';

    public function ensureRoles(): Role
    {
        Permission::findOrCreate(self::SELL_PERMISSION, 'web');

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
     *   password: string,
     *   notes?: string|null
     * }  $data
     */
    public function sell(array $data): SaasOperator
    {
        $this->ensureRoles();

        $user = User::create([
            'name' => $data['contact_name'],
            'email' => $data['email'],
            'mobile' => $data['phone'] ?? null,
            'password' => bcrypt($data['password']),
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([self::OPERATOR_ROLE]);

        return SaasOperator::create([
            'user_id' => $user->id,
            'company' => $data['company'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'plan' => $data['plan'] ?? 'standard',
            'status' => 'active',
            'can_resell' => false,
            'notes' => $data['notes'] ?? null,
            'sold_at' => now(),
        ]);
    }

    public function setStatus(SaasOperator $operator, string $status): void
    {
        $operator->update(['status' => $status]);
    }
}
