<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\EnsureTenantHasActivePlan;
use App\Http\Middleware\EnsureValidWebAccessSession;
use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\DoctorSchedule;
use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_client_admin_can_save_settings_and_configuration_page_renders(): void
    {
        [$tenant, $admin, $doctor] = $this->context();

        $this->withoutAccessMiddleware();
        $this->actingAs($admin)
            ->patch(route('client.mi-configuracion.agenda.update'), $this->settingsPayload($doctor))
            ->assertRedirect(route('client.mi-configuracion.index', ['tab' => 'agenda']));

        $this->assertDatabaseHas('appointment_settings', [
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'timezone' => 'America/Mexico_City',
            'minimum_notice_minutes' => 120,
            'is_customer_booking_enabled' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('client.mi-configuracion.index', ['tab' => 'agenda']))
            ->assertOk()
            ->assertSee('Agenda')
            ->assertSee('Configuracion incompleta');
    }

    public function test_non_admin_cannot_change_appointment_configuration(): void
    {
        [$tenant, , $doctor] = $this->context();
        $assistant = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        Role::findOrCreate('asistente', 'web');
        $assistant->assignRole('asistente');

        $this->withoutAccessMiddleware();
        $this->actingAs($assistant)
            ->patch(route('client.mi-configuracion.agenda.update'), $this->settingsPayload($doctor))
            ->assertForbidden();

        $this->assertDatabaseMissing('appointment_settings', ['tenant_id' => $tenant->id]);
    }

    public function test_doctor_and_service_from_another_tenant_are_rejected(): void
    {
        [$tenant, $admin] = $this->context();
        [, , $otherDoctor, $otherService] = $this->context('other');

        $this->withoutAccessMiddleware();
        $this->actingAs($admin)
            ->from(route('client.mi-configuracion.index', ['tab' => 'agenda']))
            ->patch(route('client.mi-configuracion.agenda.update'), $this->settingsPayload($otherDoctor))
            ->assertSessionHasErrors('doctor_user_id');

        $this->actingAs($admin)
            ->patch(route('client.mi-configuracion.agenda.services.update', $otherService), [
                'is_bookable' => 1,
                'appointment_duration_minutes' => 30,
                'appointment_buffer_minutes' => 0,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('appointment_settings', ['tenant_id' => $tenant->id]);
    }

    public function test_weekly_schedule_rejects_overlaps(): void
    {
        [$tenant, $admin, $doctor] = $this->context();
        AppointmentSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
        ]);

        $this->withoutAccessMiddleware();
        $this->actingAs($admin)
            ->post(route('client.mi-configuracion.agenda.schedules.store'), [
                'weekday' => 1,
                'starts_at' => '09:00',
                'ends_at' => '13:00',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->from(route('client.mi-configuracion.index', ['tab' => 'agenda']))
            ->post(route('client.mi-configuracion.agenda.schedules.store'), [
                'weekday' => 1,
                'starts_at' => '12:00',
                'ends_at' => '15:00',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, DoctorSchedule::where('tenant_id', $tenant->id)->count());
    }

    public function test_inactive_service_cannot_be_enabled_for_booking(): void
    {
        [, $admin, , $service] = $this->context();
        $service->update(['is_active' => false]);

        $this->withoutAccessMiddleware();
        $this->actingAs($admin)
            ->from(route('client.mi-configuracion.index', ['tab' => 'agenda']))
            ->patch(route('client.mi-configuracion.agenda.services.update', $service), [
                'is_bookable' => 1,
                'appointment_duration_minutes' => 30,
                'appointment_buffer_minutes' => 0,
            ])
            ->assertSessionHasErrors('is_bookable');

        $this->assertFalse($service->fresh()->is_bookable);
    }

    public function test_blocks_are_stored_in_utc_and_reject_overlaps(): void
    {
        [$tenant, $admin, $doctor] = $this->context();
        AppointmentSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'timezone' => 'America/Mexico_City',
        ]);

        $this->withoutAccessMiddleware();
        $this->actingAs($admin)
            ->post(route('client.mi-configuracion.agenda.blocks.store'), [
                'starts_at' => '2026-07-01T09:00',
                'ends_at' => '2026-07-01T11:00',
                'reason' => 'Ausencia',
            ])
            ->assertRedirect();

        $block = ScheduleBlock::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('2026-07-01 15:00:00', $block->starts_at->format('Y-m-d H:i:s'));

        $this->actingAs($admin)
            ->from(route('client.mi-configuracion.index', ['tab' => 'agenda']))
            ->post(route('client.mi-configuracion.agenda.blocks.store'), [
                'starts_at' => '2026-07-01T10:00',
                'ends_at' => '2026-07-01T12:00',
            ])
            ->assertSessionHasErrors('starts_at');
    }

    public function test_customer_booking_requires_complete_configuration_and_disables_when_it_becomes_incomplete(): void
    {
        [$tenant, $admin, $doctor, $service] = $this->context();

        $this->withoutAccessMiddleware();
        $this->actingAs($admin)
            ->from(route('client.mi-configuracion.index', ['tab' => 'agenda']))
            ->patch(route('client.mi-configuracion.agenda.update'), $this->settingsPayload($doctor, true))
            ->assertSessionHasErrors('is_customer_booking_enabled');

        $this->actingAs($admin)
            ->patch(route('client.mi-configuracion.agenda.update'), $this->settingsPayload($doctor))
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('client.mi-configuracion.agenda.schedules.store'), [
                'weekday' => 1,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('client.mi-configuracion.agenda.services.update', $service), [
                'is_bookable' => 1,
                'appointment_duration_minutes' => 30,
                'appointment_buffer_minutes' => 10,
                'booking_description' => 'Consulta de rutina',
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('client.mi-configuracion.agenda.update'), $this->settingsPayload($doctor, true))
            ->assertRedirect();

        $this->assertTrue($tenant->appointmentSetting()->firstOrFail()->is_customer_booking_enabled);

        $schedule = DoctorSchedule::where('tenant_id', $tenant->id)->firstOrFail();
        $this->actingAs($admin)
            ->delete(route('client.mi-configuracion.agenda.schedules.destroy', $schedule))
            ->assertRedirect();

        $this->assertFalse($tenant->appointmentSetting()->firstOrFail()->is_customer_booking_enabled);
    }

    private function context(string $suffix = 'main'): array
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Agenda '.$suffix,
            'slug' => 'agenda-'.$suffix.'-'.str()->random(6),
        ]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $doctor = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        Role::findOrCreate('client-admin', 'web');
        $admin->assignRole('client-admin');
        VeterinarianProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $doctor->id,
            'professional_name' => 'Dra. Agenda '.$suffix,
            'professional_title' => 'MVZ',
            'license_number' => 'CED-'.str()->upper(str()->random(8)),
            'is_active' => true,
        ]);
        $service = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta '.$suffix,
            'type' => 'service',
            'is_active' => true,
        ]);

        return [$tenant, $admin, $doctor, $service];
    }

    private function withoutAccessMiddleware(): void
    {
        $this->withoutMiddleware([
            EnsureValidWebAccessSession::class,
            EnsureTenantHasActivePlan::class,
            CheckTenantSubscription::class,
        ]);
    }

    private function settingsPayload(User $doctor, bool $enabled = false): array
    {
        return [
            'doctor_user_id' => $doctor->id,
            'timezone' => 'America/Mexico_City',
            'slot_interval_minutes' => 15,
            'default_duration_minutes' => 30,
            'minimum_notice_hours' => 2,            // UI en horas → BD guarda 120 min
            'booking_window_days' => 60,
            'customer_cancellation_notice_hours' => 24, // UI en horas → BD guarda 1440 min
            'proposal_hold_hours' => 24,
            'reminder_hours_before' => 24,
            'cancellation_policy' => 'no_penalty',
            'is_customer_booking_enabled' => $enabled ? 1 : 0,
        ];
    }
}
