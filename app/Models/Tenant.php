<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\Customer;
use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\AnimalTypeField;
use App\Models\AnimalFieldValue;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'business_name',
        'email',
        'phone',
        'logo',
        'status',
        'plan_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'trial_ends_at',
        'subscription_ends_at',
        'is_active',
        'created_by',
        'activation_code_token',
        'activation_link_token',
        'activation_expires_at',
        'activated_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'activation_expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static function activationCodeHash(string $code): string
    {
        return hash('sha256', 'tenant-activation-code:' . $code);
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    public function payments()
    {
        return $this->hasMany(TenantPayment::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class);
    }
    public function customers()
{
    return $this->hasMany(Customer::class);
}

public function animalTypes()
{
    return $this->hasMany(AnimalType::class);
}

public function animals()
{
    return $this->hasMany(Animal::class);
}

public function clubs()
{
    return $this->hasMany(Club::class);
}

public function animalTypeFields()
{
    return $this->hasMany(AnimalTypeField::class);
}

public function animalFieldValues()
{
    return $this->hasMany(AnimalFieldValue::class);
}
// Agrega esto junto a tus otras relaciones en App\Models\Tenant.php
public function paymentMethods()
{
    return $this->hasMany(PaymentMethod::class);
}
public function catalogItems()
{
    return $this->hasMany(CatalogItem::class);
}
// ... dentro de la clase Tenant

public function notes()
{
    return $this->hasMany(Note::class);
}

public function clientPayments()
{
    // Usamos clientPayments para diferenciarlo de tus "payments" de suscripción de Stripe
    return $this->hasMany(Payment::class);
}

public function noteDetails()
{
    return $this->hasMany(NoteDetail::class);
}

public function vaccinationLetters()
{
    return $this->hasMany(VaccinationLetter::class);
}
}
