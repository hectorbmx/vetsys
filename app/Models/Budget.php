<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'folio',
        'status',
        'budget_date',
        'valid_until',
        'notes',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
    ];

    protected $casts = [
        'budget_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function animals()
    {
        return $this->hasMany(BudgetAnimal::class);
    }

    public function items()
    {
        return $this->hasMany(BudgetItem::class);
    }
}
