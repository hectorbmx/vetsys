<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Auth\TenantSessionGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantSessionGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_plan_without_subscription_or_payment_is_limited_to_billing(): void
    {
        $user = $this->tenantUser();
        $guard = app(TenantSessionGuard::class);

        $fullAccess = $guard->canLogin($user);
        $billingAccess = $guard->canEnterBillingArea($user);

        $this->assertFalse($fullAccess['allowed']);
        $this->assertSame('needs_review', $fullAccess['billing_status']);
        $this->assertTrue($billingAccess['allowed']);
        $this->assertTrue($billingAccess['billing_limited']);
    }

    public function test_pending_payment_is_billing_limited(): void
    {
        $user = $this->tenantUser();
        $subscription = $this->subscription($user->tenant, 'pending');
        $this->payment($user->tenant, $subscription, 'pending', 1000, 'transfer');
        $guard = app(TenantSessionGuard::class);

        $fullAccess = $guard->canLogin($user);
        $billingAccess = $guard->canEnterBillingArea($user);

        $this->assertFalse($fullAccess['allowed']);
        $this->assertSame('pending_payment', $fullAccess['billing_status']);
        $this->assertTrue($billingAccess['allowed']);
        $this->assertTrue($billingAccess['billing_limited']);
    }

    public function test_trial_payment_allows_full_access_until_it_expires(): void
    {
        $user = $this->tenantUser();
        $subscription = $this->subscription($user->tenant, 'active', now()->addDays(10), true);
        $this->payment($user->tenant, $subscription, 'paid', 0, 'trial', now()->addDays(10));

        $access = app(TenantSessionGuard::class)->canLogin($user);

        $this->assertTrue($access['allowed']);
        $this->assertSame('trial_active', $access['billing_status']);
    }

    public function test_expired_trial_blocks_full_access(): void
    {
        $user = $this->tenantUser();
        $user->tenant->update([
            'trial_ends_at' => now()->subDay(),
            'subscription_ends_at' => now()->subDay(),
        ]);
        $subscription = $this->subscription($user->tenant, 'active', now()->subDay(), true);
        $this->payment($user->tenant, $subscription, 'paid', 0, 'trial', now()->subDay());

        $access = app(TenantSessionGuard::class)->canLogin($user);

        $this->assertFalse($access['allowed']);
        $this->assertSame('trial_expired', $access['billing_status']);
    }

    public function test_paid_current_subscription_allows_full_access(): void
    {
        $user = $this->tenantUser();
        $subscription = $this->subscription($user->tenant, 'active', now()->addMonth());
        $this->payment($user->tenant, $subscription, 'paid', 1000, 'cash', now()->addMonth());

        $access = app(TenantSessionGuard::class)->canLogin($user);

        $this->assertTrue($access['allowed']);
        $this->assertSame('paid_active', $access['billing_status']);
    }

    public function test_inactive_tenant_cannot_enter_billing_area(): void
    {
        $user = $this->tenantUser(['status' => 'cancelled', 'is_active' => false]);

        $access = app(TenantSessionGuard::class)->canEnterBillingArea($user);

        $this->assertFalse($access['allowed']);
        $this->assertSame('tenant_inactive', $access['code']);
    }

    private function tenantUser(array $tenantAttributes = []): User
    {
        $plan = Plan::create([
            'name' => 'Plan Test '.uniqid(),
            'slug' => 'plan-test-'.uniqid(),
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
        $tenant = Tenant::factory()->create(array_merge([
            'plan_id' => $plan->id,
            'status' => 'active',
            'is_active' => true,
        ], $tenantAttributes));

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
    }

    private function subscription(Tenant $tenant, string $status, $endsAt = null, bool $trial = false): TenantSubscription
    {
        $endsAt ??= now()->addMonth();

        if ($trial) {
            $tenant->update([
                'trial_ends_at' => $endsAt,
                'subscription_ends_at' => $endsAt,
            ]);
        } else {
            $tenant->update(['subscription_ends_at' => $endsAt]);
        }

        return TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $tenant->plan_id,
            'provider' => 'manual',
            'status' => $status,
            'starts_at' => now()->startOfDay(),
            'trial_ends_at' => $trial ? $endsAt : null,
            'ends_at' => $endsAt,
        ]);
    }

    private function payment(Tenant $tenant, TenantSubscription $subscription, string $status, float $amount, string $method, $periodEndsAt = null): TenantPayment
    {
        return TenantPayment::create([
            'tenant_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'plan_id' => $tenant->plan_id,
            'provider' => 'manual',
            'amount' => $amount,
            'currency' => 'MXN',
            'status' => $status,
            'payment_method' => $method,
            'paid_at' => $status === 'paid' ? now() : null,
            'period_starts_at' => now()->startOfDay(),
            'period_ends_at' => $periodEndsAt ?? now()->addMonth(),
        ]);
    }
}
