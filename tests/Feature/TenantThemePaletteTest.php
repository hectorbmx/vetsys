<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantThemePalettes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantThemePaletteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_without_configuration_uses_ocean(): void
    {
        $tenant = Tenant::create([
            'name' => 'Theme Default Tenant',
            'slug' => 'theme-default-'.str()->random(6),
        ]);

        $this->assertSame(TenantThemePalettes::DEFAULT, $tenant->fresh()->theme_palette);
        $this->assertSame(TenantThemePalettes::DEFAULT, TenantThemePalettes::normalize(null));
        $this->assertSame(TenantThemePalettes::DEFAULT, TenantThemePalettes::normalize('unknown'));
    }

    public function test_admin_can_save_allowed_palette_for_own_tenant(): void
    {
        [$tenant, $user] = $this->tenantUser('admin-save', 'admin');

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->patch(route('client.mi-configuracion.appearance.update'), [
                'theme_palette' => 'forest',
            ])
            ->assertRedirect(route('client.mi-configuracion.index', ['tab' => 'apariencia']));

        $this->assertSame('forest', $tenant->fresh()->theme_palette);
    }

    public function test_invalid_palette_is_rejected(): void
    {
        [$tenant, $user] = $this->tenantUser('invalid', 'admin');

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->from(route('client.mi-configuracion.index', ['tab' => 'apariencia']))
            ->patch(route('client.mi-configuracion.appearance.update'), [
                'theme_palette' => 'neon',
            ])
            ->assertRedirect(route('client.mi-configuracion.index', ['tab' => 'apariencia']))
            ->assertSessionHasErrors('theme_palette');

        $this->assertSame(TenantThemePalettes::DEFAULT, $tenant->fresh()->theme_palette);
    }

    public function test_non_admin_cannot_change_palette(): void
    {
        [$tenant, $user] = $this->tenantUser('assistant', 'asistente');

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->patch(route('client.mi-configuracion.appearance.update'), [
                'theme_palette' => 'violet',
            ])
            ->assertForbidden();

        $this->assertSame(TenantThemePalettes::DEFAULT, $tenant->fresh()->theme_palette);
    }

    public function test_palette_change_is_isolated_between_tenants(): void
    {
        [$firstTenant, $firstUser] = $this->tenantUser('first', 'admin');
        [$secondTenant] = $this->tenantUser('second', 'admin');

        $this->withoutMiddleware();
        $this->actingAs($firstUser)
            ->patch(route('client.mi-configuracion.appearance.update'), [
                'theme_palette' => 'sunset',
            ])
            ->assertRedirect();

        $this->assertSame('sunset', $firstTenant->fresh()->theme_palette);
        $this->assertSame(TenantThemePalettes::DEFAULT, $secondTenant->fresh()->theme_palette);
    }

    public function test_admin_can_upload_tenant_logo_to_r2(): void
    {
        Storage::fake('r2');
        [$tenant, $user] = $this->tenantUser('logo', 'admin');

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->patch(route('client.mi-configuracion.appearance.update'), [
                'theme_palette' => 'forest',
                'logo' => UploadedFile::fake()->image('logo.png', 128, 128),
            ])
            ->assertRedirect(route('client.mi-configuracion.index', ['tab' => 'apariencia']));

        $tenant->refresh();

        $this->assertSame('forest', $tenant->theme_palette);
        $this->assertNotNull($tenant->logo);
        $this->assertStringStartsWith('tenants/'.$tenant->id.'/branding/logo-', $tenant->logo);
        Storage::disk('r2')->assertExists($tenant->logo);
    }

    public function test_admin_can_remove_tenant_logo_from_r2(): void
    {
        Storage::fake('r2');
        [$tenant, $user] = $this->tenantUser('remove-logo', 'admin');

        Storage::disk('r2')->put('tenants/'.$tenant->id.'/branding/logo-old.png', 'old');
        $tenant->update(['logo' => 'tenants/'.$tenant->id.'/branding/logo-old.png']);

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->patch(route('client.mi-configuracion.appearance.update'), [
                'theme_palette' => 'ocean',
                'remove_logo' => '1',
            ])
            ->assertRedirect(route('client.mi-configuracion.index', ['tab' => 'apariencia']));

        Storage::disk('r2')->assertMissing('tenants/'.$tenant->id.'/branding/logo-old.png');
        $this->assertNull($tenant->fresh()->logo);
    }

    private function tenantUser(string $suffix, string $role): array
    {
        $tenant = Tenant::create([
            'name' => 'Theme Tenant '.$suffix,
            'slug' => 'theme-tenant-'.$suffix.'-'.str()->random(6),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        Role::findOrCreate($role, 'web');
        $user->assignRole($role);

        return [$tenant, $user];
    }
}
