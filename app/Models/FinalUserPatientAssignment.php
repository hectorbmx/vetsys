<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinalUserPatientAssignment extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'animal_id',
        'assigned_by',
        'assigned_at',
        'revoked_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function animal() { return $this->belongsTo(Animal::class); }
    public function assigner() { return $this->belongsTo(User::class, 'assigned_by'); }
}
