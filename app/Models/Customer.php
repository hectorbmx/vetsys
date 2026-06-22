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

public function getOutstandingBalanceAttribute(): float
{
    return (float) $this->saleNotes->sum(fn (Note $note) => max($note->balance, 0));
}

public function getCreditBalanceAttribute(): float
{
    $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
    $applied = \Illuminate\Support\Facades\DB::table('note_payments')
        ->join('payments', 'payments.id', '=', 'note_payments.payment_id')
        ->where('payments.customer_id', $this->id)
        ->sum('note_payments.amount_applied');

    return max((float) $payments->sum('amount') - (float) $applied, 0);
}

public function accountSetting()
{
    return $this->hasOne(CustomerAccountSetting::class);
}

public function statements()
{
    return $this->hasMany(CustomerStatement::class);
}
public function taxProfiles()
{
    return $this->hasMany(CustomerTaxProfile::class);
}

public function defaultTaxProfile()
{
    return $this->hasOne(CustomerTaxProfile::class)->where('is_default', true);
}
public function invoices()
{
    return $this->hasMany(Invoice::class);
}

public function portalUserLinks()
{
    return $this->hasMany(CustomerUserLink::class);
}

public function portalAccesses()
{
    return $this->hasMany(CustomerPortalAccess::class);
}

public function finalUserPatientAssignments()
{
    return $this->hasMany(FinalUserPatientAssignment::class);
}

public function animalPortalVisibilitySettings()
{
    return $this->hasMany(AnimalPortalVisibilitySetting::class);
}

public function portalNotifications()
{
    return $this->hasMany(PortalNotification::class);
}

public function appointments()
{
    return $this->hasMany(Appointment::class);
}
}
