<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteDetail extends Model
{
    protected $fillable = [
        'tenant_id', 'note_id', 'catalog_item_id', 'animal_id', 
        'quantity', 'price_at_sale', 'tax_at_sale', 'subtotal'
    ];

    public function note() { return $this->belongsTo(Note::class); }
    public function catalogItem() { return $this->belongsTo(CatalogItem::class); }
    public function animal() { return $this->belongsTo(Animal::class); }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}