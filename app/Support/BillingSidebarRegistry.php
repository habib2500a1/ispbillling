<?php

namespace App\Support;

use App\Filament\Pages\CollectionDeskReport;
use App\Filament\Resources\InvoiceResource;

final class BillingSidebarRegistry
{
    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   sort: int,
     *   count_key?: string,
     *   url: string,
     *   active_routes: list<string>,
     * }>
     */
    public static function items(): array
    {
        return array_map(
            static fn (array $item): array => [...$item, 'url' => static::resolveUrl($item)],
            static::definitions(),
        );
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   sort: int,
     *   count_key?: string,
     *   url_target: string,
     *   active_routes: list<string>,
     * }>
     */
    private static function definitions(): array
    {
        return [
            [
                'key' => 'all_bills',
                'label' => 'All bills',
                'icon' => 'heroicon-o-queue-list',
                'sort' => 1,
                'count_key' => 'all',
                'url_target' => 'invoices.index',
                'active_routes' => [
                    'filament.admin.resources.invoices.index',
                ],
            ],
            [
                'key' => 'due_bills',
                'label' => 'Due bills',
                'icon' => 'heroicon-o-exclamation-triangle',
                'sort' => 2,
                'count_key' => 'due',
                'url_target' => 'invoices.due',
                'active_routes' => [
                    'filament.admin.resources.invoices.due',
                ],
            ],
            [
                'key' => 'paid_bills',
                'label' => 'Paid bills',
                'icon' => 'heroicon-o-check-circle',
                'sort' => 3,
                'count_key' => 'paid',
                'url_target' => 'invoices.paid',
                'active_routes' => [
                    'filament.admin.resources.invoices.paid',
                ],
            ],
            [
                'key' => 'invoices',
                'label' => 'Invoices',
                'icon' => 'heroicon-o-document-text',
                'sort' => 4,
                'url_target' => 'invoices.index',
                'active_routes' => [
                    'filament.admin.resources.invoices.index',
                    'filament.admin.resources.invoices.edit',
                ],
            ],
            [
                'key' => 'new_invoice',
                'label' => 'New invoice',
                'icon' => 'heroicon-o-document-plus',
                'sort' => 5,
                'url_target' => 'invoices.create',
                'active_routes' => [
                    'filament.admin.resources.invoices.create',
                ],
            ],
            [
                'key' => 'today_collection',
                'label' => "Today's collection",
                'icon' => 'heroicon-o-calendar-days',
                'sort' => 6,
                'count_key' => 'today_collection',
                'url_target' => 'collection.today',
                'active_routes' => [
                    'filament.admin.pages.collection-desk-report',
                ],
            ],
            [
                'key' => 'all_collection',
                'label' => 'All collection',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 7,
                'url_target' => 'collection.month',
                'active_routes' => [
                    'filament.admin.pages.collection-desk-report',
                ],
            ],
        ];
    }

    /**
     * @param  array{url_target: string}  $item
     */
    private static function resolveUrl(array $item): string
    {
        return match ($item['url_target']) {
            'invoices.index' => InvoiceResource::getUrl('index'),
            'invoices.due' => InvoiceResource::getUrl('due'),
            'invoices.paid' => InvoiceResource::getUrl('paid'),
            'invoices.create' => InvoiceResource::getUrl('create'),
            'collection.today' => CollectionDeskReport::getUrl(['preset' => 'today']),
            'collection.month' => CollectionDeskReport::getUrl(['preset' => 'month']),
            default => '#',
        };
    }
}
