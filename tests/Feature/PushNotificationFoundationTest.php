<?php

namespace Tests\Feature;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Http\Middleware\EnsureApiTenantAccess;
use App\Http\Middleware\EnsureValidMobileAccessSession;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentNotificationDelivery;
use App\Models\PushDevice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationFoundationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            EnsureValidMobileAccessSession::class,
            EnsureApiTenantAccess::class,
        ]);
    }

    public function test_foundation_tables_and_after_commit_queue_configuration_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('push_devices', [
            'tenant_id', 'user_id', 'platform', 'token', 'token_hash',
            'device_uuid', 'device_name', 'app_version', 'last_seen_at', 'revoked_at',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_notification_deliveries', [
            'tenant_id', 'appointment_event_id', 'recipient_user_id', 'push_device_id',
            'channel', 'recipient_key', 'recipient_hash', 'status', 'attempts',
            'last_attempt_at', 'delivered_at', 'last_error',
        ]));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
    }

    public function test_foundation_factories_preserve_tenant_ownership(): void
    {
        $device = PushDevice::factory()->create();
        $delivery = AppointmentNotificationDelivery::factory()->create();

        $this->assertSame($device->tenant_id, $device->user->tenant_id);
        $this->assertSame($delivery->tenant_id, $delivery->appointmentEvent->tenant_id);
        $this->assertSame($delivery->tenant_id, $delivery->appointmentEvent->appointment->tenant_id);
    }

    public function test_authenticated_user_registers_and_rotates_an_encrypted_device_token(): void
    {
        [$tenant, $user] = $this->userContext();
        Sanctum::actingAs($user);
        $payload = [
            'platform' => 'ANDROID',
            'token' => 'first-fcm-token',
            'device_uuid' => 'installation-1',
            'device_name' => 'Pixel test',
            'app_version' => '1.0.0',
            'user_id' => 999999,
            'tenant_id' => 999999,
        ];

        $response = $this->postJson('/api/v1/push-devices', $payload)
            ->assertOk()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.device_uuid', 'installation-1')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.token_hash');

        $device = PushDevice::findOrFail($response->json('data.id'));
        $this->assertSame($tenant->id, $device->tenant_id);
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('first-fcm-token', $device->token);
        $this->assertSame(hash('sha256', 'first-fcm-token'), $device->token_hash);
        $this->assertNotSame('first-fcm-token', DB::table('push_devices')->where('id', $device->id)->value('token'));

        $this->postJson('/api/v1/push-devices', array_merge($payload, [
            'token' => 'rotated-fcm-token',
            'app_version' => '1.1.0',
        ]))->assertOk()->assertJsonPath('data.id', $device->id);

        $this->assertDatabaseCount('push_devices', 1);
        $this->assertDatabaseMissing('push_devices', ['token_hash' => hash('sha256', 'first-fcm-token')]);
        $this->assertDatabaseHas('push_devices', [
            'id' => $device->id,
            'token_hash' => hash('sha256', 'rotated-fcm-token'),
            'app_version' => '1.1.0',
        ]);
    }

    public function test_token_can_move_to_current_user_without_leaking_or_allowing_foreign_revocation(): void
    {
        [, $firstUser] = $this->userContext();
        [, $secondUser] = $this->userContext();
        $payload = ['platform' => 'android', 'token' => 'shared-fcm-token', 'device_uuid' => 'device-a'];

        Sanctum::actingAs($firstUser);
        $deviceId = $this->postJson('/api/v1/push-devices', $payload)->assertOk()->json('data.id');

        Sanctum::actingAs($secondUser);
        $this->postJson('/api/v1/push-devices', array_merge($payload, ['device_uuid' => 'device-b']))
            ->assertOk()
            ->assertJsonPath('data.id', $deviceId);
        $this->assertDatabaseHas('push_devices', [
            'id' => $deviceId,
            'tenant_id' => $secondUser->tenant_id,
            'user_id' => $secondUser->id,
            'device_uuid' => 'device-b',
        ]);

        Sanctum::actingAs($firstUser);
        $this->deleteJson("/api/v1/push-devices/{$deviceId}")->assertNotFound();

        Sanctum::actingAs($secondUser);
        $this->deleteJson("/api/v1/push-devices/{$deviceId}")
            ->assertOk()
            ->assertJsonPath('data.id', $deviceId);
        $this->assertNotNull(PushDevice::findOrFail($deviceId)->revoked_at);
    }

    public function test_registration_requires_authentication_and_valid_payload(): void
    {
        $this->postJson('/api/v1/push-devices', [])->assertUnauthorized();

        [, $user] = $this->userContext();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/push-devices', [
            'platform' => 'windows',
            'token' => '',
            'device_uuid' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors(['platform', 'token', 'device_uuid']);
    }

    public function test_delivery_recipient_is_deduplicated_per_event_and_channel(): void
    {
        $tenant = Tenant::factory()->create();
        $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id]);
        $event = AppointmentEvent::create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appointment->id,
            'event_type' => AppointmentEventType::Requested,
            'new_status' => AppointmentStatus::PendingTenant,
        ]);
        $recipientKey = "tenant:{$tenant->id}";
        $attributes = [
            'tenant_id' => $tenant->id,
            'appointment_event_id' => $event->id,
            'channel' => NotificationDeliveryChannel::Email,
            'recipient_key' => $recipientKey,
            'recipient_hash' => AppointmentNotificationDelivery::recipientHash($recipientKey),
            'status' => NotificationDeliveryStatus::Pending,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        AppointmentNotificationDelivery::create($attributes);

        $this->expectException(QueryException::class);
        AppointmentNotificationDelivery::create($attributes);
    }

    private function userContext(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        return [$tenant, $user];
    }
}
