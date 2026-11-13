<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Budget;
use App\Models\BudgetAnimal;
use App\Models\BudgetItem;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mobile_budget_list_is_scoped_to_the_authenticated_tenant(): void
    {
        [$user, $token] = $this->mobileUser();
        $own = $this->budgetForTenant($user->tenant_id, 'Propio');
        $foreignUser = $this->mobileUser()[0];
        $this->budgetForTenant($foreignUser->tenant_id, 'Ajeno');

        $this->withToken($token)
            ->getJson('/api/v1/budgets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_mobile_budget_detail_returns_not_found_for_another_tenant(): void
    {
        [$user, $token] = $this->mobileUser();
        $foreignUser = $this->mobileUser()[0];
        $foreignBudget = $this->budgetForTenant($foreignUser->tenant_id, 'Ajeno');

        $this->withToken($token)
            ->getJson('/api/v1/budgets/'.$foreignBudget->id)
            ->assertNotFound();
    }

    public function test_mobile_budget_detail_returns_nested_customer_animals_and_items(): void
    {
        [$user, $token] = $this->mobileUser();
        $budget = $this->budgetForTenant($user->tenant_id, 'Detalle');

        $this->withToken($token)
            ->getJson('/api/v1/budgets/'.$budget->id)
            ->assertOk()
            ->assertJsonPath('data.id', $budget->id)
            ->assertJsonPath('data.customer.full_name', 'Detalle Cliente')
            ->assertJsonPath('data.animals.0.name', 'Detalle Caballo')
            ->assertJsonPath('data.animals.0.items.0.name', 'Servicio detalle')
            ->assertJsonPath('data.animals.0.items.0.price_at_budget', '1500.00');
    }

    private function mobileUser(): array
    {
        $plan = Plan::create([
            'name' => 'Budget Mobile Plan',
            'slug' => 'budget-mobile-plan-'.str()->random(8),
            'price' => 100,
            'currency' => 'MXN',
            'billing_period' => 'monthly',
            'max_users' => 5,
            'max_clients' => 50,
            'web_access' => true,
            'mobile_access' => true,
            'max_web_sessions_per_user' => 1,
            'max_mobile_sessions_per_user' => 1,
            'allow_cross_platform_sessions' => true,
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'name' => 'Budget Tenant',
            'slug' => 'budget-tenant-'.str()->random(8),
            'email' => str()->random(8).'@example.test',
            'status' => 'active',
            'plan_id' => $plan->id,
            'is_active' => true,
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

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'budget-api-test-phone',
        ])->assertOk();

        return [$user, $login->json('token')];
    }

    private function budgetForTenant(int $tenantId, string $label): Budget
    {
        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'name' => $label,
            'last_name' => 'Cliente',
            'email' => strtolower($label).'@example.test',
            'status' => 'active',
        ]);

        $animal = Animal::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'name' => $label.' Caballo',
            'status' => 'active',
        ]);

        $budget = Budget::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'folio' => 'PRES-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => Budget::STATUS_DRAFT,
            'budget_date' => now()->toDateString(),
            'valid_until' => now()->addWeek()->toDateString(),
            'subtotal' => 1500,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 1500,
        ]);

        $budgetAnimal = BudgetAnimal::create([
            'tenant_id' => $tenantId,
            'budget_id' => $budget->id,
            'animal_id' => $animal->id,
            'position' => 0,
            'subtotal' => 1500,
        ]);

        BudgetItem::create([
            'tenant_id' => $tenantId,
            'budget_id' => $budget->id,
            'budget_animal_id' => $budgetAnimal->id,
            'animal_id' => $animal->id,
            'catalog_item_id' => null,
            'service_name_snapshot' => $label === 'Detalle' ? 'Servicio detalle' : 'Servicio',
            'quantity' => 1,
            'base_price' => 1200,
            'price_at_budget' => 1500,
            'tax_at_budget' => 0,
            'subtotal' => 1500,
            'position' => 0,
        ]);

        return $budget;
    }
}
