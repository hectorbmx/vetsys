<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MobileBillingModeContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mobile_auth_and_bootstrap_expose_normalized_billing_mode(): void
    {
        $user = $this->tenantUser('monthly');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'contract-test-phone',
        ])->assertOk();

        $login->assertJsonPath('user.tenant.billing_mode', Tenant::BILLING_MODE_MONTHLY_CUTOFF);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.tenant.billing_mode', Tenant::BILLING_MODE_MONTHLY_CUTOFF);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/bootstrap')
            ->assertOk()
            ->assertJsonPath('user.tenant.billing_mode', Tenant::BILLING_MODE_MONTHLY_CUTOFF);
    }

    public function test_mobile_contract_defaults_invalid_billing_mode_to_note_based(): void
    {
        $user = $this->tenantUser('unknown-mode');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'contract-test-phone',
        ])->assertOk();

        $login->assertJsonPath('user.tenant.billing_mode', Tenant::BILLING_MODE_NOTE_BASED);
    }

    private function tenantUser(string $billingMode): User
    {
        $plan = Plan::create([
            'name' => 'Mobile Contract Plan',
            'slug' => 'mobile-contract-plan-'.str()->random(6),
            'price' => 100,
            'currency' => 'MXN',
            'billing_period' => 'monthly',
            'max_users' => 1,
            'max_clients' => 10,
            'web_access' => true,
            'mobile_access' => true,
            'max_web_sessions_per_user' => 1,
            'max_mobile_sessions_per_user' => 1,
            'allow_cross_platform_sessions' => true,
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'name' => 'Mobile Contract Tenant',
            'slug' => 'mobile-contract-tenant-'.str()->random(6),
            'email' => str()->random(6).'@example.test',
            'status' => 'active',
            'plan_id' => $plan->id,
            'is_active' => true,
            'billing_mode' => $billingMode,
        ]);

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'provider' => 'manual',
            'status' => 'active',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addMonth(),
        ]);

        TenantPayment::create([
            'tenant_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'provider' => 'manual',
            'amount' => 100,
            'currency' => 'MXN',
            'status' => 'paid',
            'payment_method' => 'manual',
            'paid_at' => now(),
            'period_starts_at' => now()->startOfDay(),
            'period_ends_at' => now()->addMonth(),
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
    }
}
