<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CatalogItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'sku', 'type', 'description', 
        'tax_percentage', 'has_inventory', 'is_active'
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
        'has_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relación con el Tenant
    public function tenant() { return $this->belongsTo(Tenant::class); }

    // Historial completo de precios
    public function priceHistories() { return $this->hasMany(PriceHistory::class); }

    // Inventario (Relación 1 a 1 opcional)
    public function inventory() { return $this->hasOne(Inventory::class); }

    /**
     * Helper / Accessor para obtener el precio actual rápido
     */
    public function getCurrentPriceAttribute(): float
    {
        return (float) ($this->priceHistories()
            ->whereNull('end_date')
            ->first()?->price ?? 0.00);
    }
}