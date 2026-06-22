<?php

namespace App\Models;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Jobs\ProcessAppointmentEventNotifications;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class AppointmentEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'actor_user_id',
        'event_type',
        'previous_status',
        'new_status',
        'metadata',
    ];

    protected $casts = [
        'event_type' => AppointmentEventType::class,
        'previous_status' => AppointmentStatus::class,
        'new_status' => AppointmentStatus::class,
        'metadata' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function notificationDeliveries()
    {
        return $this->hasMany(AppointmentNotificationDelivery::class);
    }

    protected static function booted(): void
    {
        static::created(function (AppointmentEvent $event) {
            $eventId = $event->getKey();
            DB::afterCommit(function () use ($eventId) {
                try {
                    ProcessAppointmentEventNotifications::dispatch($eventId);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        });
        static::updating(fn () => throw new LogicException('Los eventos de cita son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los eventos de cita son inmutables.'));
    }
}
