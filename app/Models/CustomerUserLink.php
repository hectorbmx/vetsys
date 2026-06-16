<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerUserLink extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'relationship',
        'is_primary',
        'created_by',
        'revoked_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
