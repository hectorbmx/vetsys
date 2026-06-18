<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'tenant_id',
        'inventory_id',
        'catalog_item_id',
        'user_id',
        'type',
        'direction',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'notes',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_before' => 'decimal:2',
        'stock_after' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
