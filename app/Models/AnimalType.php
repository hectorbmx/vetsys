<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnimalType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animals()
    {
        return $this->hasMany(Animal::class);
    }

    public function fields()
    {
        return $this->hasMany(AnimalTypeField::class)
            ->orderBy('sort_order');
    }

    public function activeFields()
    {
        return $this->hasMany(AnimalTypeField::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}