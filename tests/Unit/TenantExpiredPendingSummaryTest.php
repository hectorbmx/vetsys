<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\TenantsController;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Services\Auth\TenantSessionGuard;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class TenantExpiredPendingSummaryTest extends TestCase
{
    public function test_expired_pending_checkout_does_not_hide_expired_subscription(): void
    {
        $tenant = $this->tenantWithPendingPeriod(now()->subDay());

        $summary = $this->adminSummary($tenant);
        $access = (new TenantSessionGuard())->billingStatus($tenant);

        $this->assertSame('expired', $summary['status']);
        $this->assertSame('subscription_expired', $access['billing_status']);
    }

    public function test_current_pending_checkout_remains_pending(): void
    {
        $tenant = $this->tenantWithPendingPeriod(now()->addDay());

        $summary = $this->adminSummary($tenant);
        $access = (new TenantSessionGuard())->billingStatus($tenant);

        $this->assertSame('pending_payment', $summary['status']);
        $this->assertSame('pending_payment', $access['billing_status']);
    }

    private function tenantWithPendingPeriod($pendingEndsAt): Tenant
    {
        $tenant = new Tenant([
            'status' => 'active', 'is_active' => true,
            'subscription_ends_at' => now()->subDays(5),
        ]);
        $tenant->setRelation('subscriptions', new Collection([
            new TenantSubscription(['status' => 'pending', 'ends_at' => $pendingEndsAt]),
        ]));
        $tenant->setRelation('payments', new Collection([
            new TenantPayment(['status' => 'pending', 'period_ends_at' => $pendingEndsAt]),
        ]));

        return $tenant;
    }

    private function adminSummary(Tenant $tenant): array
    {
        return (new ReflectionMethod(TenantsController::class, 'tenantBillingSummary'))
            ->invoke(new TenantsController(), $tenant);
    }
}
