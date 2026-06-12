<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantOnboardingStep;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantOnboardingStepTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_has_onboarding_steps_with_optional_evidence(): void
    {
        $tenant = $this->tenant('relationship');

        $step = $tenant->onboardingSteps()->create([
            'step' => TenantOnboardingStep::FIRST_CUSTOMER_CREATED,
            'completed_at' => now(),
            'evidence_type' => Tenant::class,
            'evidence_id' => $tenant->id,
        ]);

        $this->assertTrue($step->tenant->is($tenant));
        $this->assertTrue($step->evidence->is($tenant));
        $this->assertTrue($tenant->onboardingSteps->contains($step));
        $this->assertInstanceOf(\DateTimeInterface::class, $step->completed_at);
    }

    public function test_a_step_can_only_be_completed_once_per_tenant(): void
    {
        $tenant = $this->tenant('unique');

        $tenant->onboardingSteps()->create([
            'step' => TenantOnboardingStep::FIRST_SERVICE_CREATED,
            'completed_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $tenant->onboardingSteps()->create([
            'step' => TenantOnboardingStep::FIRST_SERVICE_CREATED,
            'completed_at' => now(),
        ]);
    }

    public function test_the_same_step_can_be_completed_by_different_tenants(): void
    {
        $firstTenant = $this->tenant('first');
        $secondTenant = $this->tenant('second');

        foreach ([$firstTenant, $secondTenant] as $tenant) {
            $tenant->onboardingSteps()->create([
                'step' => TenantOnboardingStep::FIRST_NOTE_CREATED,
                'completed_at' => now(),
            ]);
        }

        $this->assertSame(
            2,
            TenantOnboardingStep::whereIn('tenant_id', [$firstTenant->id, $secondTenant->id])
                ->where('step', TenantOnboardingStep::FIRST_NOTE_CREATED)
                ->count()
        );
    }

    public function test_deleting_a_tenant_deletes_its_onboarding_steps(): void
    {
        $tenant = $this->tenant('cascade');
        $step = $tenant->onboardingSteps()->create([
            'step' => TenantOnboardingStep::FIRST_ANIMAL_TYPE_CREATED,
            'completed_at' => now(),
        ]);

        $tenant->delete();

        $this->assertDatabaseMissing('tenant_onboarding_steps', ['id' => $step->id]);
    }

    public function test_it_exposes_the_supported_steps(): void
    {
        $this->assertCount(6, TenantOnboardingStep::STEPS);
        $this->assertTrue(TenantOnboardingStep::isValidStep(TenantOnboardingStep::FIRST_NOTE_CREATED));
        $this->assertFalse(TenantOnboardingStep::isValidStep('unsupported_step'));
    }

    private function tenant(string $suffix): Tenant
    {
        return Tenant::create([
            'name' => 'Onboarding Test Tenant',
            'slug' => 'onboarding-test-'.$suffix.'-'.str()->random(6),
        ]);
    }
}
