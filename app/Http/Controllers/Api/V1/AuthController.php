<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TenantSessionGuard;
use App\Services\Auth\UserAccessSessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Tu usuario todavia no esta activo.',
            ], 403);
        }

        if ($user->hasRole('super-admin')) {
            return response()->json([
                'message' => 'La app movil solo esta disponible para usuarios de tenant.',
            ], 403);
        }

        if (! $user->tenant_id) {
            return response()->json([
                'message' => 'Tu usuario no tiene una empresa asignada.',
            ], 403);
        }

        $access = app(TenantSessionGuard::class)->canLogin($user);

        if (! $access['allowed']) {
            return response()->json([
                'message' => $access['message'],
                'code' => $access['code'],
                'billing_status' => $access['billing_status'],
            ], $access['http_status'] ?? 403);
        }

        $accessManager = app(UserAccessSessionManager::class);

        if (! $accessManager->planAllows($user, 'mobile')) {
            return response()->json([
                'message' => 'Tu plan no incluye acceso a la app movil.',
            ], 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $newToken = $user->createToken($credentials['device_name'] ?? 'mobile-app');
        $accessManager->registerMobile($user, $newToken->accessToken->id, $request);

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $newToken->plainTextToken,
            'user' => $this->serializeUser($user->fresh(['tenant.plan'])),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()->load('tenant.plan')),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        app(UserAccessSessionManager::class)->revokeCurrentMobile($token?->id);
        $token?->delete();

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    public function serializeUser(User $user): array
    {
        $tenant = $user->tenant;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'business_name' => $tenant->business_name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'status' => $tenant->status,
                'is_active' => $tenant->is_active,
                'billing_mode' => $tenant->normalizedBillingMode(),
                'plan' => $tenant->plan ? [
                    'id' => $tenant->plan->id,
                    'name' => $tenant->plan->name,
                ] : null,
                'trial_ends_at' => $tenant->trial_ends_at?->toISOString(),
                'subscription_ends_at' => $tenant->subscription_ends_at?->toISOString(),
            ] : null,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
