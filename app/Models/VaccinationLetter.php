<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaccinationLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'animal_id',
        'image_path',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }
}
