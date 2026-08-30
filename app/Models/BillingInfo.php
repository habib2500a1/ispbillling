<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BillingInfo extends Model
{
    use HasFactory;
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'auto_disable_date' => 'date',
            'extra_date' => 'date',
            'paid_date' => 'datetime',
        ];
    }

    public function permanentExpireDate(): ?Carbon
    {
        return $this->auto_disable_date
            ? Carbon::parse($this->auto_disable_date)->startOfDay()
            : null;
    }

    public function temporaryExpireDate(): ?Carbon
    {
        if (! $this->extra_date) {
            return null;
        }
        $temp = Carbon::parse($this->extra_date)->startOfDay();

        return $temp->gte(now()->startOfDay()) ? $temp : null;
    }

    public function isTemporarilyExtended(): bool
    {
        return $this->temporaryExpireDate() !== null;
    }

    public function hasActiveTemporaryHold(?Carbon $asOf = null): bool
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        if (! $this->extra_date) {
            return false;
        }

        return Carbon::parse($this->extra_date)->startOfDay()->gte($asOf);
    }

    public function clearTemporaryHold(): void
    {
        if ($this->extra_date === null) {
            return;
        }
        $this->extra_date = null;
        $this->save();
    }

    public function outstandingDue(): float
    {
        return max(0, (float) ($this->due_amount ?? 0));
    }

    protected $fillable = [
        'customer_bill_unique_id',
        'monthly_rent',
        'additional_charge',
        'discount',
        'advance',
        'vat',
        'auto_disable',
        'auto_disable_date',
        'auto_disable_month',
        'extra_date',
        'billing_type',
        'billing_day',
        'grace_period_days',
        'paid_amount',
        'paid_date',
        'previous_due',
        'due_amount',
        'total_due_amount',
        'total_amount',
    ];

    // In Billing model
    public function scopeAutoDisable($query)
    {
        return $query->where('auto_disable', true);
    }

    public function scopeAutoDisableDate($query, $date)
    {
        return $query->where('auto_disable_date', '<=', $date->copy()->endOfDay());
    }

    public function scopeUnpaid($query)
    {
        return $query->where('paid_amount', 0.00);
    }

    public function scopePaid($query)
    {
        return $query->where('paid_amount', '>', 0.00);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('system');
    }

    protected static function booted()
    {
        // static::created(function ($billingInfo) {
        //     \Log::info('BillingInfo created: ' . $billingInfo->customer_bill_unique_id);
        //     // other code...
        // });

        // static::updated(function ($billingInfo) {
        //     \Log::info('BillingInfo updated: ' . $billingInfo->customer_bill_unique_id);
        //     // other code...
        // });

        // static::created(function ($billingInfo) {
        //     PaymentSummary::create([
        //         'customer_payment_unique_id' => $billingInfo->customer_bill_unique_id,
        //         'ppp_username' => CustomersInfo::where('customer_unique_id', $billingInfo->customer_bill_unique_id)->first()->ppp_username,
        //         'monthly_rent' => $billingInfo->monthly_rent,
        //         'additional_charge' => $billingInfo->additional_charge,
        //         'discount' => $billingInfo->discount,
        //         'advance' => $billingInfo->advance,
        //         'vat' => $billingInfo->vat,
        //         'previous_due' => $billingInfo->previous_due,
        //         'due_amount' => $billingInfo->due_amount,
        //         'total_due_amount' => $billingInfo->total_due_amount,
        //         'paid_amount' => $billingInfo->paid_amount,
        //         'total_amount' => $billingInfo->total_amount,
        //         'payment_date' => $billingInfo->paid_date,
        //         'collected_by' => strtok(auth()->user()->email, '@'),
        //     ]);
        // });
        // static::updated(function ($billingInfo) {
        //     // Check if the 'paid_amount' attribute has changed
        //     if ($billingInfo->wasChanged('paid_amount')) {
        //         PaymentSummary::create([
        //             'customer_payment_unique_id' => $billingInfo->customer_bill_unique_id,
        //             'ppp_username' => CustomersInfo::where('customer_unique_id', $billingInfo->customer_bill_unique_id)->first()->ppp_username,
        //             'monthly_rent' => $billingInfo->monthly_rent,
        //             'additional_charge' => $billingInfo->additional_charge,
        //             'discount' => $billingInfo->discount,
        //             'advance' => $billingInfo->advance,
        //             'vat' => $billingInfo->vat,
        //             'previous_due' => $billingInfo->previous_due,
        //             'due_amount' => $billingInfo->due_amount,
        //             'total_due_amount' => $billingInfo->total_due_amount,
        //             'paid_amount' => $billingInfo->paid_amount,
        //             'total_amount' => $billingInfo->total_amount,
        //             'payment_date' => $billingInfo->paid_date,
        //             'collected_by' => strtok(auth()->user()->email, '@'),
        //         ]);
        //     }
        // });

        // static::updated(function ($billingInfo) {
        //     Log::create([
        //         'table_name' => 'BillingInfo',
        //         'action' => 'update',
        //         'record_id' => $billingInfo->customer_bill_unique_id,
        //         'old_data' => json_encode($billingInfo->getOriginal()),
        //         'new_data' => json_encode($billingInfo->getChanges()),
        //         'user_id' => auth()->id(),
        //     ]);
        // });
    }
}
