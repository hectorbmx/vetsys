<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnimalTypeField extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'animal_type_id',
        'label',
        'slug',
        'field_type',
        'options_json',
        'is_required',
        'is_active',
        'sort_order',
        'help_text',
    ];

    protected $casts = [
        'options_json' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animalType()
    {
        return $this->belongsTo(AnimalType::class);
    }

    public function values()
    {
        return $this->hasMany(AnimalFieldValue::class);
    }
}