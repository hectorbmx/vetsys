<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
        'tenant_id',
        'catalog_item_id',
        'stock_actual',
        'stock_minimo',
        'allow_negative_stock',
    ];

    protected $casts = [
        'stock_actual' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'allow_negative_stock' => 'boolean',
    ];

    public function catalogItem() { return $this->belongsTo(CatalogItem::class); }
}
