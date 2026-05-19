<?php

namespace App\Support;

final class BillingAccountListRegistry
{
    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   route: string,
     *   icon: string,
     *   sort: int,
     *   count_key: string,
     * }>
     */
    public static function items(): array
    {
        return [
            [
                'key' => 'all',
                'label' => 'All accounts',
                'route' => 'index',
                'icon' => 'heroicon-o-users',
                'sort' => 10,
                'count_key' => 'all',
            ],
            [
                'key' => 'active',
                'label' => 'Active accounts',
                'route' => 'active',
                'icon' => 'heroicon-o-check-circle',
                'sort' => 11,
                'count_key' => 'active',
            ],
            [
                'key' => 'today',
                'label' => "Today's clients",
                'route' => 'today',
                'icon' => 'heroicon-o-user-plus',
                'sort' => 12,
                'count_key' => 'today',
            ],
            [
                'key' => 'expire_3',
                'label' => 'Expire in 3 days',
                'route' => 'expire-3',
                'icon' => 'heroicon-o-clock',
                'sort' => 13,
                'count_key' => 'expire_3',
            ],
            [
                'key' => 'expire_7',
                'label' => 'Expire in 7 days',
                'route' => 'expire-7',
                'icon' => 'heroicon-o-calendar-days',
                'sort' => 14,
                'count_key' => 'expire_7',
            ],
            [
                'key' => 'expired',
                'label' => 'Expired accounts',
                'route' => 'expired',
                'icon' => 'heroicon-o-exclamation-circle',
                'sort' => 15,
                'count_key' => 'expired',
            ],
            [
                'key' => 'pending',
                'label' => 'Pending accounts',
                'route' => 'pending',
                'icon' => 'heroicon-o-queue-list',
                'sort' => 16,
                'count_key' => 'pending',
            ],
            [
                'key' => 'suspended',
                'label' => 'Suspend accounts',
                'route' => 'suspended',
                'icon' => 'heroicon-o-pause-circle',
                'sort' => 17,
                'count_key' => 'suspended',
            ],
            [
                'key' => 'left',
                'label' => 'Left accounts',
                'route' => 'left',
                'icon' => 'heroicon-o-archive-box',
                'sort' => 18,
                'count_key' => 'left',
            ],
        ];
    }
}
