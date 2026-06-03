<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAccountSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'preferred_payment_method_id',
        'cutoff_day',
        'credit_days',
        'is_statement_enabled',
    ];

    protected $casts = [
        'cutoff_day' => 'integer',
        'credit_days' => 'integer',
        'is_statement_enabled' => 'boolean',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function preferredPaymentMethod() { return $this->belongsTo(PaymentMethod::class, 'preferred_payment_method_id'); }
}
