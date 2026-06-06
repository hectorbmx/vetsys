<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPaymentLink extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'payment_method_id',
        'token',
        'amount',
        'currency',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }

    public function getIsPayableAttribute(): bool
    {
        return $this->status === 'pending'
            && (!$this->expires_at || $this->expires_at->isFuture());
    }
}
