<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnimalShare extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'animal_id',
        'shared_with_tenant_id',
        'shared_by_user_id',
        'token',
        'is_active',
        'last_accessed_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sharedWithTenant()
    {
        return $this->belongsTo(Tenant::class, 'shared_with_tenant_id');
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function sharedByUser()
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }
}
