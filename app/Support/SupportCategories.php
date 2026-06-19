<?php

namespace App\Support;

use App\Models\Customer;

/**
 * Ticket category taxonomy — grouped options, labels, and smart priority hints.
 */
final class SupportCategories
{
    /**
     * @return array<string, string>
     */
    public static function flatOptions(): array
    {
        $options = [];
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $key => $item) {
                $options[$key] = $group['label'].' · '.$item['label'];
            }
        }

        return $options;
    }

    /**
     * @return array<string, array{label: string, icon: string, items: array<string, array{label: string, default_priority: string}>}>
     */
    public static function groups(): array
    {
        return (array) config('support_categories.groups', []);
    }

    public static function label(?string $issueType): string
    {
        if ($issueType === null || $issueType === '') {
            return '—';
        }

        foreach (self::groups() as $group) {
            if (isset($group['items'][$issueType])) {
                return $group['items'][$issueType]['label'];
            }
        }

        $legacy = (array) config('support_categories.legacy_map', []);

        return \App\Models\SupportTicket::ISSUE_TYPES[$issueType]
            ?? \App\Models\SupportTicket::ISSUE_TYPES[$legacy[$issueType] ?? '']
            ?? ucfirst(str_replace('_', ' ', $issueType));
    }

    public static function groupLabel(?string $issueType): string
    {
        if ($issueType === null || $issueType === '') {
            return 'Other';
        }

        foreach (self::groups() as $key => $group) {
            if (isset($group['items'][$issueType])) {
                return $group['label'];
            }
        }

        $legacy = (array) config('support_categories.legacy_map', []);
        $mapped = $legacy[$issueType] ?? null;
        if ($mapped !== null) {
            foreach (self::groups() as $group) {
                if (isset($group['items'][$mapped])) {
                    return $group['label'];
                }
            }
        }

        return 'Legacy';
    }

    public static function defaultPriority(?string $issueType, ?Customer $customer = null): string
    {
        $priority = 'medium';

        foreach (self::groups() as $group) {
            if (isset($group['items'][$issueType])) {
                $priority = $group['items'][$issueType]['default_priority'];
                break;
            }
        }

        $legacy = (array) config('support_categories.legacy_map', []);
        if ($priority === 'medium' && isset($legacy[$issueType])) {
            return self::defaultPriority($legacy[$issueType], $customer);
        }

        if ($customer !== null && self::isCorporate($customer)) {
            $boost = (array) config('support_categories.corporate_boost_issue_types', []);
            if (in_array($issueType, $boost, true)) {
                $priority = self::boostPriority($priority);
            }
        }

        return $priority;
    }

    /**
     * @return list<array{group: string, group_key: string, key: string, label: string, default_priority: string}>
     */
    public static function allItems(): array
    {
        $items = [];
        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['items'] as $key => $item) {
                $items[] = [
                    'group' => $group['label'],
                    'group_key' => $groupKey,
                    'key' => $key,
                    'label' => $item['label'],
                    'default_priority' => $item['default_priority'],
                ];
            }
        }

        return $items;
    }

    private static function isCorporate(Customer $customer): bool
    {
        $segment = strtolower((string) data_get($customer->meta, 'segment', ''));

        return in_array($segment, ['corporate', 'business', 'enterprise', 'vip'], true)
            || (bool) data_get($customer->meta, 'is_corporate', false);
    }

    private static function boostPriority(string $priority): string
    {
        return match ($priority) {
            'low' => 'medium',
            'medium' => 'high',
            'high' => 'critical',
            default => $priority,
        };
    }
}
