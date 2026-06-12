<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\DashboardController;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantOnboardingStep;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DashboardOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_reconciles_and_exposes_presented_onboarding_steps(): void
    {
        [$tenant, $user] = $this->tenantUser('progress');
        Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dashboard Customer',
            'status' => 'active',
        ]);
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $view = app(DashboardController::class)->index();
        $onboarding = $view->getData()['onboarding'];

        $this->assertSame(1, $onboarding['completed']);
        $this->assertSame(TenantOnboardingStep::FIRST_ANIMAL_TYPE_CREATED, $onboarding['next_step']);
        $this->assertSame('Crea una raza o tipo de animal', $onboarding['steps'][0]['label']);
        $this->assertTrue($onboarding['steps'][0]['is_next']);
        $this->assertTrue($onboarding['steps'][3]['completed']);
        $this->assertDatabaseHas('tenant_onboarding_steps', [
            'tenant_id' => $tenant->id,
            'step' => TenantOnboardingStep::FIRST_CUSTOMER_CREATED,
        ]);

        $html = $view->render();

        $this->assertStringContainsString('Ruta hacia tu primera venta', $html);
        $this->assertStringContainsString('1 de 6 completados', $html);
        $this->assertStringContainsString('Crear tipo de animal', $html);
    }

    public function test_dashboard_shows_compact_state_when_onboarding_is_complete(): void
    {
        [$tenant, $user] = $this->tenantUser('complete');

        foreach (TenantOnboardingStep::STEPS as $step) {
            $tenant->onboardingSteps()->create([
                'step' => $step,
                'completed_at' => now(),
            ]);
        }

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $view = app(DashboardController::class)->index();
        $html = $view->render();

        $this->assertTrue($view->getData()['onboarding']['is_completed']);
        $this->assertStringContainsString('Ruta inicial completa', $html);
        $this->assertStringContainsString('6 de 6 completados', $html);
        $this->assertStringNotContainsString('Crear tipo de animal', $html);
    }

    private function tenantUser(string $suffix): array
    {
        $tenant = Tenant::create([
            'name' => 'Dashboard Onboarding Tenant',
            'slug' => 'dashboard-onboarding-'.$suffix.'-'.str()->random(6),
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        return [$tenant, $user];
    }
}
