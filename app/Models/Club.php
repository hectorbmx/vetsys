<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Club extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animals()
    {
        return $this->hasMany(Animal::class);
    }
}