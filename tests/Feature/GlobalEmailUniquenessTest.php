<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\GloballyUniqueEmail;
use App\Services\CustomerPortalAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalEmailUniquenessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_email_cannot_be_reused_across_users_or_customers(): void
    {
        $tenant = Tenant::factory()->create(['email' => 'tenant@example.test']);
        User::factory()->create(['email' => 'user@example.test']);
        Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'email' => 'customer@example.test',
            'status' => 'active',
        ]);

        $this->assertEmailRejected('user@example.test');
        $this->assertEmailRejected('customer@example.test');
        $this->assertEmailAccepted('tenant@example.test');
        $this->assertEmailAccepted('available@example.test');
    }

    public function test_current_record_can_keep_its_email_when_ignored(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'email' => 'same@example.test',
            'status' => 'active',
        ]);

        $validator = Validator::make(
            ['email' => 'same@example.test'],
            ['email' => ['required', 'email', new GloballyUniqueEmail('customers', $customer->id)]]
        );

        $this->assertFalse($validator->fails());
    }

    public function test_portal_activation_rejects_internal_user_email_collision(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.test',
        ]);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'email' => 'owner@example.test',
            'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya existe un usuario interno con ese correo.');

        app(CustomerPortalAccessService::class)->activate($customer, $actor);
    }

    private function assertEmailRejected(string $email): void
    {
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', new GloballyUniqueEmail]]
        );

        $this->assertTrue($validator->fails(), "Expected {$email} to be rejected.");
    }

    private function assertEmailAccepted(string $email): void
    {
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', new GloballyUniqueEmail]]
        );

        $this->assertFalse($validator->fails(), "Expected {$email} to be accepted.");
    }
}
