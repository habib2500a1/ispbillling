<?php

namespace App\Services\Saas;

use App\Models\SaasPlan;

final class SaasPlanCatalog
{
    public const MODULES = [
        'customers' => 'Customers',
        'billing' => 'Billing & collection',
        'olt' => 'OLT',
        'onu' => 'ONU',
        'mikrotik' => 'MikroTik / routers',
        'hotspot' => 'Hotspot',
        'sms' => 'SMS',
        'tickets' => 'Support tickets',
        'hr' => 'HR',
        'accounts' => 'Accounts',
        'calldesk' => 'Call desk',
        'noc' => 'NOC',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function defaults(): array
    {
        $all = array_keys(self::MODULES);

        return [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'monthly_price' => 2000,
                'yearly_price' => 20000,
                'per_user_rate' => 15,
                'max_customers' => 200,
                'max_olts' => 1,
                'max_onus' => 200,
                'max_routers' => 2,
                'max_staff' => 5,
                'modules' => ['customers', 'billing', 'mikrotik', 'tickets', 'sms'],
                'sort_order' => 10,
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'monthly_price' => 4000,
                'yearly_price' => 40000,
                'per_user_rate' => 10,
                'max_customers' => 1000,
                'max_olts' => 3,
                'max_onus' => 1000,
                'max_routers' => 5,
                'max_staff' => 15,
                'modules' => array_values(array_diff($all, ['noc'])),
                'sort_order' => 20,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'monthly_price' => 8000,
                'yearly_price' => 80000,
                'per_user_rate' => 8,
                'max_customers' => 5000,
                'max_olts' => 8,
                'max_onus' => 5000,
                'max_routers' => 15,
                'max_staff' => 40,
                'modules' => $all,
                'sort_order' => 30,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'monthly_price' => 15000,
                'yearly_price' => 150000,
                'per_user_rate' => 5,
                'max_customers' => 0,
                'max_olts' => 0,
                'max_onus' => 0,
                'max_routers' => 0,
                'max_staff' => 0,
                'modules' => ['*'],
                'sort_order' => 40,
            ],
        ];
    }

    public function seed(): void
    {
        foreach ($this->defaults() as $row) {
            SaasPlan::query()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_active' => true])
            );
        }
    }

    public function resolve(string $slug): SaasPlan
    {
        $this->seed();

        return SaasPlan::query()->where('slug', $slug)->firstOrFail();
    }
}
