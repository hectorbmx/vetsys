<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantPortalSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'is_portal_enabled',
        'is_mobile_access_enabled',
        'access_mode',
        'default_access_status',
        'requires_manual_activation',
        'monthly_price',
        'currency',
        'trial_days',
        'created_by',
    ];

    protected $casts = [
        'is_portal_enabled' => 'boolean',
        'is_mobile_access_enabled' => 'boolean',
        'requires_manual_activation' => 'boolean',
        'monthly_price' => 'decimal:2',
        'trial_days' => 'integer',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
