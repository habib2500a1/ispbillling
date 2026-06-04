<?php

namespace App\Filament\Resources\PromotionalOfferResource\Pages;

use App\Filament\Resources\PromotionalOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotionalOffer extends EditRecord
{
    protected static string $resource = PromotionalOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
