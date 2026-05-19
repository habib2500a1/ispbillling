<?php

namespace App\Filament\Resources\BandwidthClientResource\Pages;

use App\Filament\Pages\GenerateBandwidthInvoice;
use App\Filament\Resources\BandwidthClientPaymentResource;
use App\Filament\Resources\BandwidthClientResource;
use App\Models\BandwidthClient;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBandwidthClients extends ListRecords
{
    protected static string $resource = BandwidthClientResource::class;

    protected static string $view = 'filament.resources.bandwidth-client-resource.pages.list-bandwidth-clients';

    /**
     * @return array<string, int|float>
     */
    public function getBwStats(): array
    {
        $clients = BandwidthClient::query()->get();

        return [
            'total' => $clients->count(),
            'active' => $clients->where('status', 'active')->count(),
            'profile_total' => (float) $clients->sum('profile_total'),
            'due_total' => (float) $clients->sum(fn (BandwidthClient $c): float => $c->totalDue()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('payment_history')
                ->label('Payment history')
                ->icon('heroicon-o-wallet')
                ->color('success')
                ->outlined()
                ->url(BandwidthClientPaymentResource::getUrl()),
            Actions\Action::make('generate_invoice')
                ->label('Generate invoice')
                ->icon('heroicon-o-document-plus')
                ->color('gray')
                ->outlined()
                ->url(GenerateBandwidthInvoice::getUrl()),
            Actions\CreateAction::make()
                ->label('Add client')
                ->icon('heroicon-o-plus'),
        ];
    }
}
