<?php

namespace App\Filament\Resources\ResellerResource\Pages;

use App\Filament\Pages\ResellersHub;
use App\Filament\Resources\ResellerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResellers extends ListRecords
{
    protected static string $resource = ResellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('hub')
                ->label('Reseller hub')
                ->icon('heroicon-o-home')
                ->url(ResellersHub::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
