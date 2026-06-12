<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantOnboardingStep;
use App\Services\TenantOnboardingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class TenantOnboardingServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_reconciles_real_tenant_data_and_returns_progress(): void
    {
        $tenant = $this->tenant('complete');
        $scenario = $this->completeScenario($tenant);

        $status = app(TenantOnboardingService::class)->reconcile($tenant);

        $this->assertSame(6, $status['completed']);
        $this->assertSame(100, $status['percentage']);
        $this->assertTrue($status['is_completed']);
        $this->assertNull($status['next_step']);
        $this->assertSame(6, $tenant->onboardingSteps()->count());

        $paidStep = $tenant->onboardingSteps()
            ->where('step', TenantOnboardingStep::FIRST_NOTE_PAID)
            ->firstOrFail();

        $this->assertTrue($paidStep->evidence->is($scenario['note']));
    }

    public function test_it_ignores_inactive_empty_cancelled_and_unpaid_data(): void
    {
        $tenant = $this->tenant('ignored');
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Customer',
            'status' => 'inactive',
        ]);
        $animalType = AnimalType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Type',
            'slug' => 'inactive-type-'.str()->random(6),
            'is_active' => false,
        ]);
        $item = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Service',
            'sku' => 'INACTIVE-'.str()->random(6),
            'type' => 'service',
            'is_active' => false,
        ]);
        Animal::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_type_id' => $animalType->id,
            'name' => 'Inactive Pet',
            'status' => 'inactive',
        ]);
        $note = Note::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'folio' => 'VT-IGNORED-'.str()->random(5),
            'total' => 0,
            'status' => 'PAGADA',
            'date_at' => now()->toDateString(),
        ]);
        $note->details()->create([
            'tenant_id' => $tenant->id,
            'catalog_item_id' => $item->id,
            'quantity' => 1,
            'price_at_sale' => 0,
            'tax_at_sale' => 0,
            'subtotal' => 0,
        ]);

        $status = app(TenantOnboardingService::class)->reconcile($tenant);

        $this->assertSame(0, $status['completed']);
        $this->assertSame(0, $status['percentage']);
        $this->assertSame(TenantOnboardingStep::CLINIC_CONFIGURED, $status['next_step']);
    }

    public function test_a_paid_note_requires_a_real_paid_payment_application(): void
    {
        $tenant = $this->tenant('payment');
        $scenario = $this->completeScenario($tenant, false);

        $service = app(TenantOnboardingService::class);
        $status = $service->reconcile($tenant);

        $this->assertSame(5, $status['completed']);
        $this->assertSame(TenantOnboardingStep::FIRST_NOTE_PAID, $status['next_step']);

        $payment = Payment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $scenario['customer']->id,
            'payment_method_id' => $scenario['paymentMethod']->id,
            'amount' => 100,
            'provider' => 'manual',
            'status' => 'paid',
        ]);
        $scenario['note']->payments()->attach($payment->id, ['amount_applied' => 100]);

        $status = $service->reconcile($tenant);

        $this->assertSame(6, $status['completed']);
        $this->assertTrue($status['is_completed']);
    }

    public function test_completed_steps_do_not_regress_when_evidence_is_removed(): void
    {
        $tenant = $this->tenant('persistent');
        $scenario = $this->completeScenario($tenant);
        $service = app(TenantOnboardingService::class);

        $service->reconcile($tenant);
        $scenario['customer']->delete();

        $status = $service->reconcile($tenant);

        $this->assertSame(6, $status['completed']);
        $this->assertTrue($status['is_completed']);
    }

    public function test_mark_completed_is_idempotent_and_rejects_unknown_steps(): void
    {
        $tenant = $this->tenant('manual');
        $service = app(TenantOnboardingService::class);

        $first = $service->markCompleted($tenant, TenantOnboardingStep::FIRST_SERVICE_CREATED);
        $second = $service->markCompleted($tenant, TenantOnboardingStep::FIRST_SERVICE_CREATED);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $tenant->onboardingSteps()->count());

        $this->expectException(InvalidArgumentException::class);
        $service->markCompleted($tenant, 'unknown_step');
    }

    public function test_unknown_persisted_steps_do_not_change_progress(): void
    {
        $tenant = $this->tenant('unknown-persisted');
        $tenant->onboardingSteps()->create([
            'step' => 'legacy_unknown_step',
            'completed_at' => now(),
        ]);

        $status = app(TenantOnboardingService::class)->statusFor($tenant);

        $this->assertSame(0, $status['completed']);
        $this->assertSame(0, $status['percentage']);
        $this->assertCount(6, $status['steps']);
    }

    public function test_data_from_another_tenant_never_completes_steps(): void
    {
        $tenantWithData = $this->tenant('with-data');
        $emptyTenant = $this->tenant('empty');
        $this->completeScenario($tenantWithData);

        $status = app(TenantOnboardingService::class)->reconcile($emptyTenant);

        $this->assertSame(0, $status['completed']);
        $this->assertSame(0, $emptyTenant->onboardingSteps()->count());
    }

    public function test_safe_reconciliation_returns_progress_without_changing_rules(): void
    {
        $tenant = $this->tenant('safe');

        $status = app(TenantOnboardingService::class)->reconcileSafely($tenant);

        $this->assertNotNull($status);
        $this->assertSame(0, $status['completed']);
        $this->assertSame(TenantOnboardingStep::CLINIC_CONFIGURED, $status['next_step']);
    }

    private function completeScenario(Tenant $tenant, bool $withPayment = true): array
    {
        $animalType = AnimalType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dog',
            'slug' => 'dog-'.str()->random(6),
            'is_active' => true,
        ]);
        $paymentMethod = PaymentMethod::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cash',
            'slug' => 'cash-'.str()->random(6),
            'is_active' => true,
        ]);
        $item = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consultation',
            'sku' => 'SERVICE-'.str()->random(6),
            'type' => 'service',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Active Customer',
            'status' => 'active',
        ]);
        $animal = Animal::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_type_id' => $animalType->id,
            'name' => 'Active Pet',
            'status' => 'active',
        ]);
        $note = Note::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'folio' => 'VT-ONBOARDING-'.str()->random(5),
            'total' => 100,
            'status' => 'PAGADA',
            'date_at' => now()->toDateString(),
        ]);
        $note->details()->create([
            'tenant_id' => $tenant->id,
            'catalog_item_id' => $item->id,
            'animal_id' => $animal->id,
            'quantity' => 1,
            'price_at_sale' => 100,
            'tax_at_sale' => 0,
            'subtotal' => 100,
        ]);

        if ($withPayment) {
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => 100,
                'provider' => 'manual',
                'status' => 'paid',
            ]);
            $note->payments()->attach($payment->id, ['amount_applied' => 100]);
        }

        return compact('animalType', 'paymentMethod', 'item', 'customer', 'animal', 'note');
    }

    private function tenant(string $suffix): Tenant
    {
        return Tenant::create([
            'name' => 'Onboarding Service Test Tenant',
            'slug' => 'onboarding-service-'.$suffix.'-'.str()->random(6),
        ]);
    }
}
