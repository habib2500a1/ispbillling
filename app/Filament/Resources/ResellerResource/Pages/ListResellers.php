<?php

namespace App\Filament\Resources\ResellerResource\Pages;

use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResellers extends ListRecords
{
    protected static string $resource = ResellerResource::class;

    protected static string $view = 'filament.resources.reseller-resource.pages.list-resellers';

    /**
     * @return array<string, int|float>
     */
    public function getResellerStats(): array
    {
        return [
            'total' => (int) Reseller::query()->count(),
            'active' => (int) Reseller::query()->where('is_active', true)->count(),
            'wallet_total' => (float) Reseller::query()->sum('wallet_balance'),
            'pending_commission' => (float) ResellerCommission::query()
                ->where('status', ResellerCommission::STATUS_PENDING)
                ->sum('commission_amount'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add reseller')
                ->icon('heroicon-o-plus'),
        ];
    }
}
