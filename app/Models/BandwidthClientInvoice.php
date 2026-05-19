<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BandwidthClientInvoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'bandwidth_client_id',
        'invoice_number',
        'period_month',
        'period_year',
        'amount',
        'amount_paid',
        'status',
        'due_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(BandwidthClient::class, 'bandwidth_client_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BandwidthClientPayment::class);
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->amount - (float) $this->amount_paid);
    }
}
