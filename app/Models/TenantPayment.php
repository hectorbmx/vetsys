<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantPayment extends Model
{
    use HasFactory;
    protected $fillable = [
    'tenant_id',
    'tenant_subscription_id',
    'plan_id',
    'provider',
    'provider_payment_id',
    'provider_invoice_id',
    'amount',
    'currency',
    'status',
    'payment_method',
    'payment_reference',
    'paid_at',
    'period_starts_at',
    'period_ends_at',
    'created_by',
    'notes',
];

protected $casts = [
    'paid_at' => 'datetime',
    'period_starts_at' => 'datetime',
    'period_ends_at' => 'datetime',
];

public function plan()
{
    return $this->belongsTo(Plan::class);
}

public function subscription()
{
    return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
}
}
