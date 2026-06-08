<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBillingProfile extends Model
{
    protected $fillable = [
        'tenant_id',
        'facturapi_organization_id',
        'facturapi_api_key',
        'legal_name',
        'tax_id',
        'tax_system',
        'zip',
        'csd_cer_path',
        'csd_key_path',
        'csd_password',
        'email',
        'csd_uploaded',
        'is_active',
    ];

    protected $casts = [
        'csd_uploaded' => 'boolean',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    /**
     * Tenant propietario del perfil fiscal.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Facturas emitidas usando este perfil fiscal.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}