<?php

namespace App\Filament\Resources\InventorySaleResource\Pages;

use App\Filament\Pages\InventoryHub;
use App\Filament\Resources\InventorySaleResource;
use App\Models\InventorySale;
use App\Models\InventorySaleItem;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewInventorySale extends ViewRecord
{
    protected static string $resource = InventorySaleResource::class;

    protected static string $view = 'filament.resources.inventory-sale-resource.pages.view-inventory-sale';

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_receipt')
                ->label('Thermal print')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn (InventorySale $record): string => route('inventory-sales.receipt', $record).'?print=1')
                ->openUrlInNewTab(),
            Actions\Action::make('pdf_receipt')
                ->label('PDF receipt')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (InventorySale $record): string => route('inventory-sales.receipt.pdf', $record))
                ->openUrlInNewTab(),
            Actions\Action::make('inventory_hub')
                ->label('Inventory center')
                ->icon('heroicon-o-cube')
                ->color('gray')
                ->url(InventoryHub::getUrl()),
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var InventorySale $record */
        $record = parent::resolveRecord($key);

        return $record->load(['items.product', 'warehouse', 'recorder']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Sale')
                ->schema([
                    Infolists\Components\TextEntry::make('sale_number'),
                    Infolists\Components\TextEntry::make('sold_at')->dateTime(),
                    Infolists\Components\TextEntry::make('channel')->badge(),
                    Infolists\Components\TextEntry::make('payment_method'),
                    Infolists\Components\TextEntry::make('customer_name')->placeholder('Walk-in'),
                    Infolists\Components\TextEntry::make('customer_phone')->placeholder('—'),
                    Infolists\Components\TextEntry::make('warehouse.name')->label('Warehouse')->placeholder('—'),
                    Infolists\Components\TextEntry::make('recorder.name')->label('Recorded by')->placeholder('—'),
                ])
                ->columns(3),
            Infolists\Components\Section::make('Totals')
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')->money('BDT'),
                    Infolists\Components\TextEntry::make('discount')->money('BDT'),
                    Infolists\Components\TextEntry::make('total')->money('BDT')->weight('bold'),
                    Infolists\Components\TextEntry::make('total_cost')->label('COGS')->money('BDT'),
                    Infolists\Components\TextEntry::make('gross_profit')->label('Gross profit')->money('BDT')->color('success'),
                ])
                ->columns(5),
            Infolists\Components\Section::make('Items')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->schema([
                            Infolists\Components\ImageEntry::make('product.image_path')
                                ->label('Photo')
                                ->disk('public')
                                ->height(48)
                                ->width(48)
                                ->defaultImageUrl(fn (InventorySaleItem $record): string => 'https://ui-avatars.com/api/?name='
                                    .urlencode(mb_substr((string) $record->description, 0, 2))
                                    .'&background=f97316&color=fff&size=96'),
                            Infolists\Components\TextEntry::make('description'),
                            Infolists\Components\TextEntry::make('quantity'),
                            Infolists\Components\TextEntry::make('unit_price')->money('BDT'),
                            Infolists\Components\TextEntry::make('line_total')->money('BDT'),
                            Infolists\Components\TextEntry::make('line_profit')->money('BDT')->color('success'),
                        ])
                        ->columns(6),
                ]),
        ]);
    }
}
