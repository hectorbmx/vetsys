<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Animal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_uuid',
        'synced_from_mobile',
        'customer_id',
        'club_id',
        'animal_type_id',
        'name',
        'photo_path',
        'sex',
        'birthdate',
        'color',
        'weight',
        'microchip',
        'microchip_image_path',
        'microchip_print_token',
        'microchip_issued_by',
        'microchip_pdf_disk',
        'microchip_pdf_path',
        'microchip_finalized_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'weight' => 'decimal:2',
        'synced_from_mobile' => 'boolean',
        'microchip_finalized_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function animalType()
    {
        return $this->belongsTo(AnimalType::class);
    }

    public function fieldValues()
    {
        return $this->hasMany(AnimalFieldValue::class);
    }
    // ... dentro de la clase Animal

public function noteDetails()
{
    return $this->hasMany(NoteDetail::class);
}
public function club()
{
    return $this->belongsTo(Club::class);
}

public function vaccinationLetters()
{
    return $this->hasMany(VaccinationLetter::class);
}

public function videos()
{
    return $this->hasMany(AnimalVideo::class);
}

public function radiologyStudies()
{
    return $this->hasMany(RadiologyStudy::class);
}

public function reports()
{
    return $this->hasMany(AnimalReport::class);
}

public function microchipIssuer()
{
    return $this->belongsTo(User::class, 'microchip_issued_by');
}

public function shares()
{
    return $this->hasMany(AnimalShare::class);
}

public function finalUserPatientAssignments()
{
    return $this->hasMany(FinalUserPatientAssignment::class);
}

public function portalVisibilitySettings()
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
