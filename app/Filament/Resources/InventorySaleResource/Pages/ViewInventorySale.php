<?php

namespace App\Filament\Resources\InventorySaleResource\Pages;

use App\Filament\Resources\InventorySaleResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewInventorySale extends ViewRecord
{
    protected static string $resource = InventorySaleResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Sale')
                ->schema([
                    Infolists\Components\TextEntry::make('sale_number'),
                    Infolists\Components\TextEntry::make('sold_at')->dateTime(),
                    Infolists\Components\TextEntry::make('channel')->badge(),
                    Infolists\Components\TextEntry::make('payment_method'),
                    Infolists\Components\TextEntry::make('customer_name')->placeholder('—'),
                    Infolists\Components\TextEntry::make('customer_phone')->placeholder('—'),
                ])->columns(3),
            Infolists\Components\Section::make('Totals')
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')->money('BDT'),
                    Infolists\Components\TextEntry::make('discount')->money('BDT'),
                    Infolists\Components\TextEntry::make('total')->money('BDT'),
                    Infolists\Components\TextEntry::make('total_cost')->label('COGS')->money('BDT'),
                    Infolists\Components\TextEntry::make('gross_profit')->label('Gross profit')->money('BDT')->color('success'),
                ])->columns(5),
            Infolists\Components\Section::make('Items')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->schema([
                            Infolists\Components\TextEntry::make('description'),
                            Infolists\Components\TextEntry::make('quantity'),
                            Infolists\Components\TextEntry::make('unit_price')->money('BDT'),
                            Infolists\Components\TextEntry::make('line_total')->money('BDT'),
                            Infolists\Components\TextEntry::make('line_profit')->money('BDT')->color('success'),
                        ])
                        ->columns(5),
                ]),
        ]);
    }
}
