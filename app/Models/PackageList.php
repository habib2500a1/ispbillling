<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageList extends Model
{
    use BelongsToSaasOperator;
    use HasFactory;

    protected $fillable = [
        'saas_operator_id',
        'package', 'price', 'description', 'merchant_company',
        'plan_label', 'speed', 'features', 'is_featured', 'show_on_site', 'sort_order',
        'mikrotik_rate_limit', 'push_to_mikrotik', 'mikrotik_local_address', 'mikrotik_remote_address',
        'router_name',
        'reseller_id',
    ];

    protected $casts = [
        'features' => 'array',
        'is_featured' => 'boolean',
        'show_on_site' => 'boolean',
        'push_to_mikrotik' => 'boolean',
    ];

    public function router()
    {
        return $this->belongsTo(RouterList::class, 'router_name', 'router_name');
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    /**
     * Local package names for a router (used when MikroTik is offline).
     *
     * @return list<string>
     */
    public static function namesForRouter(?string $routerName): array
    {
        return static::query()
            ->when($routerName, function ($q) use ($routerName) {
                $q->where(function ($inner) use ($routerName) {
                    $inner->where('router_name', $routerName)->orWhereNull('router_name');
                });
            })
            ->orderBy('package')
            ->pluck('package')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
