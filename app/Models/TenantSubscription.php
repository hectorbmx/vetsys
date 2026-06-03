<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSubscription extends Model
{
    use HasFactory;
    protected $fillable = [
    'tenant_id',
    'plan_id',
    'provider',
    'provider_subscription_id',
    'provider_customer_id',
    'status',
    'starts_at',
    'trial_ends_at',
    'ends_at',
    'cancelled_at',
    'created_by',
    'notes',
];

protected $casts = [
    'starts_at' => 'datetime',
    'trial_ends_at' => 'datetime',
    'ends_at' => 'datetime',
    'cancelled_at' => 'datetime',
];

public function plan()
{
    return $this->belongsTo(Plan::class);
}

public function tenant()
{
    return $this->belongsTo(Tenant::class);
}
}
