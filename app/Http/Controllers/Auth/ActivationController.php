<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ActivationController extends Controller
{
    public function show(?string $token = null)
    {
        $tenant = null;

        if ($token) {
            $tenant = Tenant::query()
                ->where('activation_link_token', hash('sha256', $token))
                ->whereNull('activated_at')
                ->firstOrFail();

            if ($tenant->activation_expires_at && $tenant->activation_expires_at->isPast()) {
                abort(403, 'La activacion ha expirado.');
            }
        }

        return view('auth.activate', compact('token', 'tenant'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'activation_token' => ['nullable', 'string'],
            'code' => ['required_without:activation_token', 'nullable', 'digits:6'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tenant = $this->findPendingTenant($validated);

        if (!$tenant) {
            return back()
                ->withErrors(['code' => 'El codigo/link o correo no coinciden con un tenant pendiente.'])
                ->withInput($request->only('email'));
        }

        if ($tenant->activation_expires_at && $tenant->activation_expires_at->isPast()) {
            return back()
                ->withErrors(['code' => 'La activacion ha expirado. Solicita un acceso nuevo.'])
                ->withInput($request->only('email'));
        }

        if (User::where('email', $tenant->email)->exists() || Customer::where('email', $tenant->email)->exists()) {
            return back()
                ->withErrors(['email' => 'Ya existe un usuario con este correo.'])
                ->withInput($request->only('email'));
        }

        DB::transaction(function () use ($tenant, $validated) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $tenant->business_name ?: $tenant->name,
                'email' => $tenant->email,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'email_verified_at' => now(),
                'invitation_token' => null,
                'invitation_link_token' => null,
                'invitation_expires_at' => null,
                'invitation_accepted_at' => now(),
            ]);

            $user->assignRole('admin');

            $tenant->update([
                'status' => 'active',
                'is_active' => true,
                'activation_code_token' => null,
                'activation_link_token' => null,
                'activation_expires_at' => null,
                'activated_at' => now(),
            ]);
        });

        return redirect()
            ->route('login')
            ->with('status', 'Cuenta activada correctamente. Ya puedes iniciar sesion.');
    }

    private function findPendingTenant(array $validated): ?Tenant
    {
        $query = Tenant::query()
            ->where('email', $validated['email'])
            ->whereNull('activated_at');

        if (!empty($validated['activation_token'])) {
            return $query
                ->where('activation_link_token', hash('sha256', $validated['activation_token']))
                ->first();
        }

        return $query
            ->where('activation_code_token', Tenant::activationCodeHash($validated['code']))
            ->first();
    }
}
