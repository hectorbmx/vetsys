<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_period',
        'max_users',
        'max_clients',
        'web_access',
        'mobile_access',
        'max_web_sessions_per_user',
        'max_mobile_sessions_per_user',
        'allow_cross_platform_sessions',
        'trial_days',
        'stripe_product_id',
        'stripe_price_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'web_access' => 'boolean',
        'mobile_access' => 'boolean',
        'allow_cross_platform_sessions' => 'boolean',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
