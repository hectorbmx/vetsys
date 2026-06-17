<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalPortalVisibilitySetting;
use App\Models\AnimalType;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\FinalUserPatientAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerPortalAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerPortalAnimalVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_quick_toggle_preserves_granular_visibility_settings(): void
    {
        [$animal, $actor, $portalUser] = $this->scenario();

        $assignment = FinalUserPatientAssignment::create([
            'tenant_id' => $animal->tenant_id,
            'customer_id' => $animal->customer_id,
            'user_id' => $portalUser->id,
            'animal_id' => $animal->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);
        $visibility = AnimalPortalVisibilitySetting::create([
            'tenant_id' => $animal->tenant_id,
            'customer_id' => $animal->customer_id,
            'user_id' => $portalUser->id,
            'animal_id' => $animal->id,
            'show_profile' => true,
            'show_history' => false,
            'show_videos' => true,
            'updated_by' => $actor->id,
        ]);

        $service = app(CustomerPortalAccessService::class);

        $this->assertFalse($service->toggleAnimalVisibility($animal, $actor));
        $this->assertNotNull($assignment->fresh()->revoked_at);
        $this->assertTrue($visibility->fresh()->show_profile);
        $this->assertFalse($visibility->fresh()->show_history);
        $this->assertTrue($visibility->fresh()->show_videos);

        $this->assertTrue($service->toggleAnimalVisibility($animal, $actor));
        $this->assertNull($assignment->fresh()->revoked_at);
        $this->assertTrue($visibility->fresh()->show_profile);
        $this->assertFalse($visibility->fresh()->show_history);
    }

    public function test_first_quick_share_enables_all_sections_by_default(): void
    {
        [$animal, $actor] = $this->scenario();

        $this->assertTrue(app(CustomerPortalAccessService::class)->toggleAnimalVisibility($animal, $actor));

        $visibility = AnimalPortalVisibilitySetting::query()
            ->where('animal_id', $animal->id)
            ->firstOrFail();

        foreach (CustomerPortalAccessService::VISIBILITY_FIELDS as $field) {
            $this->assertTrue($visibility->{$field}, "Expected {$field} to be enabled.");
        }
    }

    private function scenario(): array
    {
        $tenant = Tenant::create([
            'name' => 'Portal Visibility Tenant',
            'slug' => 'portal-visibility-'.str()->random(8),
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $portalUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'last_name' => 'Portal',
            'email' => str()->random(8).'@example.test',
            'status' => 'active',
        ]);
        $type = AnimalType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Caballo',
            'slug' => 'caballo-'.str()->random(8),
            'is_active' => true,
        ]);
        $animal = Animal::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_type_id' => $type->id,
            'name' => 'Paciente Portal',
            'sex' => 'unknown',
            'status' => 'active',
        ]);

        CustomerPortalAccess::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $portalUser->id,
            'status' => 'active',
            'billing_mode' => 'free',
            'activated_by' => $actor->id,
            'activated_at' => now(),
        ]);

        return [$animal->load('customer'), $actor, $portalUser];
    }
}
