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
        'public_token',
        'image_path',
        'pdf_disk',
        'pdf_path',
        'finalized_at',
        'date',
        'vaccine_name',
        'visible_to_customer',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'date' => 'date',
        'visible_to_customer' => 'boolean',
        'published_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (VaccinationLetter $letter) {
            $letter->public_token ??= str()->random(48);
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
