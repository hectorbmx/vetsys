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

    protected static function booted()
    {
        static::creating(function ($item) {
            if (empty($item->sku)) {
                $item->sku = self::generateNextSku($item->tenant_id, $item->type);
            }
        });
    }

    public static function generateNextSku($tenantId, $type)
    {
        $prefix = ($type === 'service') ? 'SERV' : 'PROD';

        // Buscamos todos los SKUs que empiecen con el prefijo para este tenant
        // y extraemos la parte numérica para encontrar el máximo real
        $maxNumber = self::where('tenant_id', $tenantId)
            ->where('sku', 'LIKE', "{$prefix}-%")
            ->get()
            ->map(function ($item) use ($prefix) {
                // Extraemos solo los números después del guión
                if (preg_match('/' . $prefix . '-(\d+)/', $item->sku, $matches)) {
                    return (int) $matches[1];
                }
                return 0;
            })
            ->max();

        $newNumber = ($maxNumber ?? 0) + 1;

        // Retornamos con padding de al menos 3 ceros (SERV-001, SERV-010, etc)
        $newSku = $prefix . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        // Doble verificación: si por alguna razón el SKU generado ya existe (ej. fue manual antes),
        // seguimos incrementando hasta encontrar uno libre.
        while (self::where('tenant_id', $tenantId)->where('sku', $newSku)->exists()) {
            $newNumber++;
            $newSku = $prefix . '-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
        }

        return $newSku;
    }

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