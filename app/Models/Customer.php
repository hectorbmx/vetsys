<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_uuid',
        'synced_from_mobile',
        'name',
        'last_name',
        'email',
        'phone',
        'secondary_phone',
        'address',
        'notes',
        'status',
    ];

    protected $casts = [
        'synced_from_mobile' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animals()
    {
        return $this->hasMany(Animal::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute(): string
    {
        return trim(
            $this->name . ' ' . $this->last_name
        );
    }
    // ... dentro de la clase Customer

public function saleNotes()  // antes: notes()
{
    return $this->hasMany(Note::class);
}

public function payments()
{
    return $this->hasMany(Payment::class);
}

public function paymentLinks()
{
    return $this->hasMany(CustomerPaymentLink::class);
}

public function accountSetting()
{
    return $this->hasOne(CustomerAccountSetting::class);
}

public function statements()
{
    return $this->hasMany(CustomerStatement::class);
}
}
