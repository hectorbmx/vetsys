<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles,Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'created_by',
        'invitation_token',
        'invitation_link_token',
        'invitation_expires_at',
        'invitation_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'invitation_expires_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function accessSessions()
    {
        return $this->hasMany(UserAccessSession::class);
    }

    public function veterinarianProfile()
    {
        return $this->hasOne(VeterinarianProfile::class);
    }

    public function customerLinks()
    {
        return $this->hasMany(CustomerUserLink::class);
    }

    public function customerPortalAccesses()
    {
        return $this->hasMany(CustomerPortalAccess::class);
    }

    public function finalUserPatientAssignments()
    {
        return $this->hasMany(FinalUserPatientAssignment::class);
    }

    public function animalPortalVisibilitySettings()
    {
        return $this->hasMany(AnimalPortalVisibilitySetting::class);
    }

    public function portalNotifications()
    {
        return $this->hasMany(PortalNotification::class);
    }

    public static function activationCodeHash(string $code): string
    {
        return hash('sha256', 'activation-code:'.$code);
    }
}
