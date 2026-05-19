<?php

namespace App\Filament\Resources\BandwidthClientPaymentResource\Pages;

use App\Filament\Resources\BandwidthClientPaymentResource;
use App\Models\BandwidthClient;
use App\Models\BandwidthClientInvoice;
use App\Services\Bandwidth\BandwidthClientBillingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBandwidthClientPayment extends CreateRecord
{
    protected static string $resource = BandwidthClientPaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $client = BandwidthClient::query()->findOrFail($data['bandwidth_client_id']);
        $invoice = filled($data['bandwidth_client_invoice_id'] ?? null)
            ? BandwidthClientInvoice::query()->find($data['bandwidth_client_invoice_id'])
            : null;

        return app(BandwidthClientBillingService::class)->recordPayment(
            $client,
            (float) $data['amount'],
            $invoice,
            (string) ($data['method'] ?? 'cash'),
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );
    }
}
