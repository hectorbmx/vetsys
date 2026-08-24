<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'budget_id',
        'budget_animal_id',
        'animal_id',
        'catalog_item_id',
        'service_name_snapshot',
        'quantity',
        'base_price',
        'price_at_budget',
        'tax_at_budget',
        'subtotal',
        'notes',
        'position',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'base_price' => 'decimal:2',
        'price_at_budget' => 'decimal:2',
        'tax_at_budget' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'position' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetAnimal()
    {
        return $this->belongsTo(BudgetAnimal::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
