<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\DashboardController;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardServicePerformanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DashboardServicePerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_builds_twelve_months_with_service_value_proportional_collection_and_tenant_isolation(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = $this->customer($tenant);
        $service = $this->item($tenant, 'service', 'Consulta');
        $product = $this->item($tenant, 'product', 'Medicamento');
        $method = $this->paymentMethod($tenant);

        $january = $this->note($tenant, $customer, 'JAN-001', '2026-01-10', 200);
        $this->detail($january, $service, 2, 100);
        $this->detail($january, $product, 1, 100);
        $this->pay($tenant, $customer, $method, $january, 100, 'paid');
        $this->pay($tenant, $customer, $method, $january, 100, 'pending');

        $march = $this->note($tenant, $customer, 'MAR-001', '2026-03-12', 150);
        $this->detail($march, $service, 1, 150);
        $this->pay($tenant, $customer, $method, $march, 200, 'paid');

        $cancelled = $this->note($tenant, $customer, 'FEB-001', '2026-02-10', 300, 'CANCELADA');
        $this->detail($cancelled, $service, 3, 300);

        $old = $this->note($tenant, $customer, 'OLD-001', '2025-01-10', 500);
        $this->detail($old, $service, 5, 500);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = $this->customer($otherTenant);
        $otherService = $this->item($otherTenant, 'service', 'Otro servicio');
        $otherNote = $this->note($otherTenant, $otherCustomer, 'OTHER-001', '2026-01-10', 900);
        $this->detail($otherNote, $otherService, 9, 900);

        $result = app(DashboardServicePerformanceService::class)->forYear($tenant, 2026);

        $this->assertCount(12, $result['months']);
        $this->assertSame(2.0, $result['months'][0]['service_count']);
        $this->assertSame(100.0, $result['months'][0]['service_value']);
        $this->assertSame(50.0, $result['months'][0]['collected']);
        $this->assertSame(50.0, $result['months'][0]['debt']);
        $this->assertSame(0.0, $result['months'][1]['service_value']);
        $this->assertSame(150.0, $result['months'][2]['collected']);
        $this->assertSame([
            'service_count' => 3.0,
            'service_value' => 250.0,
            'collected' => 200.0,
            'debt' => 50.0,
        ], $result['totals']);
    }

    public function test_dashboard_renders_the_annual_service_chart_after_recent_notes(): void
    {
        Carbon::setTestNow('2026-06-22 10:00:00');
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);

        $html = app(DashboardController::class)->index()->render();

        $this->assertStringContainsString('Notas Recientes', $html);
        $this->assertStringContainsString('Servicios realizados 2026', $html);
        $this->assertStringContainsString('Valor ejecutado', $html);
        $this->assertStringContainsString('Por cobrar', $html);
        $this->assertTrue(strpos($html, 'Notas Recientes') < strpos($html, 'Servicios realizados 2026'));
        Carbon::setTestNow();
    }

    private function customer(Tenant $tenant): Customer
    {
        return Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'last_name' => str()->random(8),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
    }

    private function item(Tenant $tenant, string $type, string $name): CatalogItem
    {
        return CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'has_inventory' => false,
        ]);
    }

    private function paymentMethod(Tenant $tenant): PaymentMethod
    {
        return PaymentMethod::create([
            'tenant_id' => $tenant->id,
            'name' => 'Efectivo',
            'slug' => 'efectivo-'.str()->random(6),
            'is_active' => true,
        ]);
    }

    private function note(
        Tenant $tenant,
        Customer $customer,
        string $folio,
        string $date,
        float $total,
        string $status = 'PENDIENTE',
    ): Note {
        return Note::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'folio' => $folio,
            'total' => $total,
            'status' => $status,
            'date_at' => $date,
        ]);
    }

    private function detail(Note $note, CatalogItem $item, float $quantity, float $subtotal): void
    {
        $note->details()->create([
            'tenant_id' => $note->tenant_id,
            'catalog_item_id' => $item->id,
            'quantity' => $quantity,
            'price_at_sale' => $subtotal / $quantity,
            'tax_at_sale' => 0,
            'subtotal' => $subtotal,
        ]);
    }

    private function pay(
        Tenant $tenant,
        Customer $customer,
        PaymentMethod $method,
        Note $note,
        float $amount,
        string $status,
    ): void {
        $payment = Payment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'status' => $status,
        ]);
        DB::table('note_payments')->insert([
            'note_id' => $note->id,
            'payment_id' => $payment->id,
            'amount_applied' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
