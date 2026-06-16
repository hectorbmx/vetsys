<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalNotification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'animal_id',
        'type',
        'title',
        'body',
        'url',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function animal() { return $this->belongsTo(Animal::class); }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
