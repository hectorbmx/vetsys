<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnimalFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'animal_id',
        'animal_type_field_id',

        'value_text',
        'value_number',
        'value_decimal',
        'value_date',
        'value_datetime',
        'value_boolean',
        'value_json',
        'file_path',
    ];

    protected $casts = [
        'value_decimal' => 'decimal:4',
        'value_date' => 'date',
        'value_datetime' => 'datetime',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function field()
    {
        return $this->belongsTo(AnimalTypeField::class, 'animal_type_field_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getValueAttribute()
    {
        return match ($this->field?->field_type) {
            'text',
            'textarea',
            'select',
            'file',
            'image'
                => $this->value_text,

            'number'
                => $this->value_number,

            'decimal'
                => $this->value_decimal,

            'date'
                => $this->value_date,

            'datetime'
                => $this->value_datetime,

            'checkbox',
            'multiselect'
                => $this->value_json,

            'boolean'
                => $this->value_boolean,

            default
                => $this->value_text,
        };
    }
}