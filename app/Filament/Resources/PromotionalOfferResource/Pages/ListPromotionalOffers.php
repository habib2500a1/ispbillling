<?php

namespace App\Filament\Resources\PromotionalOfferResource\Pages;

use App\Filament\Resources\PromotionalOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotionalOffers extends ListRecords
{
    protected static string $resource = PromotionalOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
