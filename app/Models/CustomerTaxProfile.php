<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTaxProfile extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'facturapi_customer_id',
        'legal_name',
        'tax_id',
        'tax_system',
        'zip',
        'email',
        'cfdi_use',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}