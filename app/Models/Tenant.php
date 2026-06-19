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
use Illuminate\Support\Facades\Storage;
use Throwable;

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
        'onboarding_banner_dismissed_at',
        'theme_palette',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'activation_expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'onboarding_banner_dismissed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static function activationCodeHash(string $code): string
    {
        return hash('sha256', 'tenant-activation-code:' . $code);
    }

    public function logoUrl(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (filter_var($this->logo, FILTER_VALIDATE_URL)) {
            return $this->logo;
        }

        try {
            if (Storage::disk('public')->exists($this->logo)) {
                return Storage::disk('public')->url($this->logo);
            }

            return Storage::disk('r2')->temporaryUrl($this->logo, now()->addMinutes(60));
        } catch (Throwable) {
            return null;
        }
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function documentSetting()
    {
        return $this->hasOne(TenantDocumentSetting::class);
    }

    public function documentTemplates()
    {
        return $this->hasMany(TenantDocumentTemplate::class);
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

public function animalVideos()
{
    return $this->hasMany(AnimalVideo::class);
}

public function radiologyStudies()
{
    return $this->hasMany(RadiologyStudy::class);
}

public function radiologyImages()
{
    return $this->hasMany(RadiologyImage::class);
}
/**
 * Configuración fiscal del tenant.
 */
public function billingProfile()
{
    return $this->hasOne(TenantBillingProfile::class);
}
public function invoices()
{
    return $this->hasMany(Invoice::class);
}

public function onboardingSteps()
{
    return $this->hasMany(TenantOnboardingStep::class);
}

public function portalSetting()
{
    return $this->hasOne(TenantPortalSetting::class);
}

public function customerUserLinks()
{
    return $this->hasMany(CustomerUserLink::class);
}

public function customerPortalAccesses()
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

public function inventoryMovements()
{
    return $this->hasMany(InventoryMovement::class);
}
}
