<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminTenantUsersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_master_can_update_a_tenant_user_login_email(): void
    {
        $tenant = Tenant::factory()->create(['email' => 'contact@example.test']);
        $admin = User::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Old Name',
            'email' => 'old@example.test',
        ]);

        $this->withoutMiddleware();

        $this->actingAs($admin)
            ->patch(route('admin.tenants.users.update', [$tenant, $user]), [
                'name' => 'Carlos Gorozpe',
                'email' => 'contact@example.test',
            ])
            ->assertRedirect(route('admin.tenants.show', $tenant));

        $user->refresh();

        $this->assertSame('Carlos Gorozpe', $user->name);
        $this->assertSame('contact@example.test', $user->email);
    }

    public function test_master_cannot_update_tenant_user_to_customer_email(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'email' => 'customer@example.test',
            'status' => 'active',
        ]);

        $this->withoutMiddleware();

        $this->actingAs($admin)
            ->from(route('admin.tenants.show', $tenant))
            ->patch(route('admin.tenants.users.update', [$tenant, $user]), [
                'name' => $user->name,
                'email' => 'customer@example.test',
            ])
            ->assertRedirect(route('admin.tenants.show', $tenant))
            ->assertSessionHasErrors('email');
    }

    public function test_master_can_delete_a_tenant_user(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->withoutMiddleware();

        $this->actingAs($admin)
            ->delete(route('admin.tenants.users.destroy', [$tenant, $user]))
            ->assertRedirect(route('admin.tenants.show', $tenant));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
