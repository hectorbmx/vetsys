<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'payment_method_id',
        'provider',
        'provider_payment_id',
        'provider_session_id',
        'status',
        'amount',
        'reference',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }
    
    public function notes()
    {
        return $this->belongsToMany(Note::class, 'note_payments')
                    ->withPivot('amount_applied')
                    ->withTimestamps();
    }
    
}
