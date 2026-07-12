<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryWarehouse extends Model
{
    protected $table = 'inventory_warehouses';

    protected $fillable = [
        'code',
        'name',
        'address',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(InventoryPurchaseOrder::class, 'warehouse_id');
    }

    public function displayLabel(): string
    {
        return trim(($this->code ? $this->code.' — ' : '').$this->name);
    }
}
