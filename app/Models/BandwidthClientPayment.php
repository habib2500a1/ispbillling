<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthClientPayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'bandwidth_client_id',
        'bandwidth_client_invoice_id',
        'amount',
        'paid_at',
        'method',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(BandwidthClient::class, 'bandwidth_client_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BandwidthClientInvoice::class, 'bandwidth_client_invoice_id');
    }
}
