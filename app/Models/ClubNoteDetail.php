<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubNoteDetail extends Model
{
    protected $fillable = [
        'tenant_id',
        'club_note_id',
        'catalog_item_id',
        'quantity',
        'price_at_sale',
        'tax_at_sale',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price_at_sale' => 'decimal:2',
        'tax_at_sale' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function clubNote()
    {
        return $this->belongsTo(ClubNote::class);
    }

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
