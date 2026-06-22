<?php

namespace App\Models;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentNotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'appointment_event_id',
        'recipient_user_id',
        'push_device_id',
        'channel',
        'recipient_key',
        'recipient_hash',
        'status',
        'attempts',
        'last_attempt_at',
        'delivered_at',
        'last_error',
    ];

    protected $casts = [
        'channel' => NotificationDeliveryChannel::class,
        'status' => NotificationDeliveryStatus::class,
        'last_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appointmentEvent()
    {
        return $this->belongsTo(AppointmentEvent::class);
    }

    public function recipientUser()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function pushDevice()
    {
        return $this->belongsTo(PushDevice::class);
    }

    public static function recipientHash(string $recipientKey): string
    {
        return hash('sha256', $recipientKey);
    }
}
