<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPortalAccess extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'status',
        'billing_mode',
        'activated_by',
        'activated_at',
        'access_starts_at',
        'access_ends_at',
        'trial_ends_at',
        'last_paid_at',
        'next_billing_at',
        'revoked_at',
        'revoked_by',
        'notes',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'access_starts_at' => 'datetime',
        'access_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'last_paid_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function activator() { return $this->belongsTo(User::class, 'activated_by'); }
    public function revoker() { return $this->belongsTo(User::class, 'revoked_by'); }
}
