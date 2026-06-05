<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadiologyImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'animal_id',
        'radiology_study_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'label',
        'notes',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function study()
    {
        return $this->belongsTo(RadiologyStudy::class, 'radiology_study_id');
    }
}
