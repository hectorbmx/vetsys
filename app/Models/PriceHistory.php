<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    protected $fillable = ['tenant_id', 'catalog_item_id', 'price', 'start_date', 'end_date'];

    protected $casts = [
        'price' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function catalogItem() { return $this->belongsTo(CatalogItem::class); }
}