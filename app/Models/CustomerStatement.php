<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerStatement extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'period_start',
        'period_end',
        'cutoff_day',
        'previous_balance',
        'period_charges',
        'period_payments',
        'ending_balance',
        'pdf_path',
        'generated_at',
        'status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'cutoff_day' => 'integer',
        'previous_balance' => 'decimal:2',
        'period_charges' => 'decimal:2',
        'period_payments' => 'decimal:2',
        'ending_balance' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
