<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnimalPortalVisibilitySetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'animal_id',
        'show_profile',
        'show_history',
        'show_notes',
        'show_services',
        'show_products',
        'show_files',
        'show_videos',
        'show_radiology',
        'show_statement',
        'show_vaccines',
        'show_appointments',
        'updated_by',
    ];

    protected $casts = [
        'show_profile' => 'boolean',
        'show_history' => 'boolean',
        'show_notes' => 'boolean',
        'show_services' => 'boolean',
        'show_products' => 'boolean',
        'show_files' => 'boolean',
        'show_videos' => 'boolean',
        'show_radiology' => 'boolean',
        'show_statement' => 'boolean',
        'show_vaccines' => 'boolean',
        'show_appointments' => 'boolean',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function animal() { return $this->belongsTo(Animal::class); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
