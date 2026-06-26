<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\UserAccessSession;
use App\Services\Auth\UserAccessSessionManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantBillingAccessHttpTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operational_route_shows_restricted_screen_for_billing_limited_tenant(): void
    {
        $user = $this->tenantUserWithPendingPayment();

        $response = $this->actingAs($user)
            ->withSession([UserAccessSessionManager::WEB_SESSION_KEY => $this->webAccessId($user)])
            ->get(route('client.dashboard'));

        $response->assertStatus(402);
        $response->assertSee('Pago pendiente');
        $response->assertSee('Ir a facturacion');
    }

    public function test_profile_remains_available_for_billing_limited_tenant(): void
    {
        $user = $this->tenantUserWithPendingPayment();

        $response = $this->actingAs($user)
            ->withSession([UserAccessSessionManager::WEB_SESSION_KEY => $this->webAccessId($user)])
            ->get(route('client.profile.index'));

        $response->assertOk();
        $response->assertSee('Pago pendiente');
    }

    private function tenantUserWithPendingPayment(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $plan = Plan::create([
            'name' => 'Plan HTTP '.uniqid(),
            'slug' => 'plan-http-'.uniqid(),
            'price' => 1000,
            'currency' => 'MXN',
            'billing_period' => 'monthly',
            'max_users' => 2,
            'max_clients' => 50,
            'web_access' => true,
            'mobile_access' => true,
            'max_web_sessions_per_user' => 1,
            'max_mobile_sessions_per_user' => 1,
            'allow_cross_platform_sessions' => true,
            'trial_days' => 30,
            'is_active' => true,
            'sort_order' => 999,
        ]);
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $user->assignRole('admin');
        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'provider' => 'manual',
            'status' => 'pending',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addMonth(),
        ]);
        TenantPayment::create([
            'tenant_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'provider' => 'manual',
            'amount' => 1000,
            'currency' => 'MXN',
            'status' => 'pending',
            'payment_method' => 'stripe_checkout',
            'payment_reference' => 'https://checkout.stripe.test/session',
            'period_starts_at' => now()->startOfDay(),
            'period_ends_at' => now()->addMonth(),
        ]);

        return $user;
    }

    private function webAccessId(User $user): int
    {
        return UserAccessSession::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'platform' => 'web',
            'session_id' => 'test-session',
            'last_activity_at' => now(),
        ])->id;
    }
}
