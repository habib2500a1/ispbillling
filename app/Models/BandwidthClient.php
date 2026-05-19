<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BandwidthClient extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'client_code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'profile_total',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'profile_total' => 'decimal:2',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BandwidthClientInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BandwidthClientPayment::class);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function totalDue(): float
    {
        return (float) $this->invoices()
            ->whereIn('status', ['due', 'partial', 'overdue'])
            ->get()
            ->sum(fn (BandwidthClientInvoice $invoice): float => $invoice->balanceDue());
    }

    public function dueInvoicesCount(): int
    {
        return (int) $this->invoices()
            ->whereIn('status', ['due', 'partial', 'overdue'])
            ->get()
            ->filter(fn (BandwidthClientInvoice $invoice): bool => $invoice->balanceDue() > 0.009)
            ->count();
    }

    public function contactLabel(): string
    {
        $parts = array_filter([
            $this->contact_person,
            $this->phone,
            $this->email,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }
}
