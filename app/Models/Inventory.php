<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['tenant_id', 'catalog_item_id', 'stock_actual', 'stock_minimo'];

    protected $casts = [
        'stock_actual' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
    ];

    public function catalogItem() { return $this->belongsTo(CatalogItem::class); }
}