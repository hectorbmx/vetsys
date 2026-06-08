<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_billing_profile_id',
        'customer_id',
        'customer_tax_profile_id',
        'note_id',
        'facturapi_invoice_id',
        'uuid',
        'series',
        'folio',
        'status',
        'cfdi_type',
        'cfdi_use',
        'payment_form',
        'payment_method',
        'subtotal',
        'tax_total',
        'total',
        'pdf_path',
        'xml_path',
        'error_message',
        'facturapi_response',
        'issued_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'facturapi_response' => 'array',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function tenantBillingProfile()
    {
        return $this->belongsTo(TenantBillingProfile::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerTaxProfile()
    {
        return $this->belongsTo(CustomerTaxProfile::class);
    }

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}