<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAccessSession;
use App\Services\Auth\UserAccessSessionManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserAccessSessionManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_plan_platform_permissions_are_independent(): void
    {
        $user = $this->tenantUser([
            'web_access' => true,
            'mobile_access' => false,
            'max_web_sessions_per_user' => 1,
            'max_mobile_sessions_per_user' => 0,
        ]);

        $manager = app(UserAccessSessionManager::class);

        $this->assertTrue($manager->planAllows($user, 'web'));
        $this->assertFalse($manager->planAllows($user, 'mobile'));
    }

    public function test_new_mobile_login_revokes_previous_mobile_token(): void
    {
        $user = $this->tenantUser([
            'mobile_access' => true,
            'max_mobile_sessions_per_user' => 1,
            'allow_cross_platform_sessions' => true,
        ]);
        $manager = app(UserAccessSessionManager::class);
        $firstToken = $user->createToken('first');
        $secondToken = $user->createToken('second');

        $firstAccess = $manager->registerMobile($user, $firstToken->accessToken->id, $this->mobileRequest('first'));
        $manager->registerMobile($user, $secondToken->accessToken->id, $this->mobileRequest('second'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $firstToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $secondToken->accessToken->id]);
        $this->assertNotNull($firstAccess->fresh()->revoked_at);
    }

    public function test_mobile_login_revokes_web_when_cross_platform_is_disabled(): void
    {
        $user = $this->tenantUser([
            'mobile_access' => true,
            'max_mobile_sessions_per_user' => 1,
            'allow_cross_platform_sessions' => false,
        ]);
        UserAccessSession::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'platform' => 'web',
            'session_id' => 'old-web-session',
            'last_activity_at' => now(),
        ]);
        $token = $user->createToken('mobile');

        app(UserAccessSessionManager::class)->registerMobile(
            $user,
            $token->accessToken->id,
            $this->mobileRequest('mobile')
        );

        $this->assertNotNull(UserAccessSession::where('session_id', 'old-web-session')->value('revoked_at'));
    }

    public function test_new_web_login_revokes_previous_web_access(): void
    {
        $user = $this->tenantUser();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('client.dashboard'));

        $firstAccess = UserAccessSession::where('user_id', $user->id)->where('platform', 'web')->firstOrFail();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('client.dashboard'));

        $this->assertNotNull($firstAccess->fresh()->revoked_at);
        $this->assertSame(1, UserAccessSession::where('user_id', $user->id)->where('platform', 'web')->whereNull('revoked_at')->count());
    }

    public function test_mobile_login_is_rejected_when_plan_does_not_include_it(): void
    {
        $user = $this->tenantUser(['mobile_access' => false, 'max_mobile_sessions_per_user' => 0]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-phone',
        ])->assertForbidden()->assertJson([
            'message' => 'Tu plan no incluye acceso a la app movil.',
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    private function tenantUser(array $planOverrides = []): User
    {
        $plan = Plan::create(array_merge([
            'name' => 'Test Plan',
            'slug' => 'test-plan-'.str()->random(6),
            'price' => 100,
            'currency' => 'MXN',
            'billing_period' => 'monthly',
            'max_users' => 1,
            'max_clients' => 10,
            'web_access' => true,
            'mobile_access' => false,
            'max_web_sessions_per_user' => 1,
            'max_mobile_sessions_per_user' => 0,
            'allow_cross_platform_sessions' => false,
            'is_active' => true,
        ], $planOverrides));

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-'.str()->random(6),
            'email' => str()->random(6).'@example.test',
            'status' => 'active',
            'plan_id' => $plan->id,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ])->load('tenant.plan');
    }

    private function mobileRequest(string $deviceName): Request
    {
        return Request::create('/api/v1/auth/login', 'POST', ['device_name' => $deviceName]);
    }
}
