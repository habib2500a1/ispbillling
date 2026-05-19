<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLead extends Model
{
    use BelongsToTenant;

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'area_id',
        'zone_id',
        'address',
        'source',
        'status',
        'assigned_to',
        'package_id',
        'estimated_mrr',
        'next_follow_up_at',
        'converted_customer_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_mrr' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }
}
