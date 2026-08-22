<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadiologyStudy extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'animal_id',
        'modality',
        'name',
        'study_date',
        'notes',
        'visible_to_customer',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'study_date' => 'date',
        'visible_to_customer' => 'boolean',
        'published_at' => 'datetime',
    ];

    public const MODALITY_RADIOLOGY = 'radiology';
    public const MODALITY_ULTRASOUND = 'ultrasound';

    public static function modalities(): array
    {
        return [
            self::MODALITY_RADIOLOGY,
            self::MODALITY_ULTRASOUND,
        ];
    }

    public function isUltrasound(): bool
    {
        return $this->modality === self::MODALITY_ULTRASOUND;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function images()
    {
        return $this->hasMany(RadiologyImage::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
