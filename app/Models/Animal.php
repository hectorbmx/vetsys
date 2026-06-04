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
        'status',
        'notes',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'weight' => 'decimal:2',
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
}
