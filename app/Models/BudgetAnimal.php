<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetAnimal extends Model
{
    protected $fillable = [
        'tenant_id',
        'budget_id',
        'animal_id',
        'position',
        'notes',
        'subtotal',
    ];

    protected $casts = [
        'position' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function items()
    {
        return $this->hasMany(BudgetItem::class);
    }
}
