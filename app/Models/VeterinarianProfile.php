<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VeterinarianProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'professional_name',
        'professional_title',
        'license_number',
        'specialty',
        'professional_phone',
        'professional_email',
        'professional_address',
        'signature_disk',
        'signature_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_user_id', 'user_id');
    }

    public function isComplete(): bool
    {
        return filled($this->professional_name)
            && filled($this->professional_title)
            && filled($this->license_number)
            && filled($this->signature_path);
    }
}
