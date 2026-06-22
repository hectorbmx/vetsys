<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantAdminRoleNormalizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_copies_legacy_admin_assignments_and_permissions_to_client_admin(): void
    {
        $legacyRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $canonicalRole = Role::firstOrCreate(['name' => 'client-admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'test-manage-tenant', 'guard_name' => 'web']);
        $legacyRole->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($legacyRole);

        $this->runNormalizationMigration();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->fresh()->hasRole('client-admin'));
        $this->assertTrue($canonicalRole->fresh()->hasPermissionTo($permission));
        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_normalization_is_idempotent(): void
    {
        $legacyRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($legacyRole);

        $this->runNormalizationMigration();
        $this->runNormalizationMigration();

        $canonicalRoleId = Role::where('name', 'client-admin')->where('guard_name', 'web')->value('id');
        $this->assertSame(2, DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->count());
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $canonicalRoleId,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    private function runNormalizationMigration(): void
    {
        $migration = require database_path('migrations/2026_06_19_000001_normalize_tenant_admin_role.php');
        $migration->up();
    }
}
