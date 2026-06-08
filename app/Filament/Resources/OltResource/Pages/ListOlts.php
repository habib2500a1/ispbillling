<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use App\Filament\Resources\OltResource\Pages\Concerns\UsesOltListLayout;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOlts extends ListRecords
{
    use UsesOltListLayout;

    protected static string $resource = OltResource::class;

    protected static string $view = 'filament.resources.olt-resource.pages.list-olts';

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-olt-module',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
