<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\StripePlanSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanesController extends Controller
{
    public function index()
    {
        $plans = Plan::orderBy('sort_order')->paginate(10);

        return view('admin.planes.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.planes.create');
    }

    public function edit(Plan $plane)
    {
        return view('admin.planes.edit', ['plan' => $plane]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPlan($request);

        $validated['slug'] = $validated['slug']
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $validated = $this->withBooleanCapabilities($request, $validated);

        Plan::create($validated);

        return redirect()
            ->route('admin.planes.index')
            ->with('success', 'Plan creado correctamente.');
    }

    public function update(Request $request, Plan $plane)
    {
        $validated = $this->validatedPlan($request, $plane);
        $validated['slug'] = $validated['slug']
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);
        $validated = $this->withBooleanCapabilities($request, $validated);

        $plane->update($validated);

        return redirect()
            ->route('admin.planes.index')
            ->with('success', 'Plan actualizado correctamente.');
    }

    private function validatedPlan(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:plans,slug'.($plan ? ','.$plan->id : '')],
            'description' => ['nullable', 'string'],

            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:3'],

            'billing_period' => [
                'required',
                'in:monthly,yearly,one_time,free',
            ],

            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_clients' => ['nullable', 'integer', 'min:1'],
            'max_web_sessions_per_user' => ['required', 'integer', 'min:0'],
            'max_mobile_sessions_per_user' => ['required', 'integer', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0'],

            'stripe_product_id' => ['nullable', 'string'],
            'stripe_price_id' => ['nullable', 'string'],
        ]);
    }

    private function withBooleanCapabilities(Request $request, array $validated): array
    {
        $validated['is_active'] = $request->boolean('is_active');
        $validated['web_access'] = $request->boolean('web_access');
        $validated['mobile_access'] = $request->boolean('mobile_access');
        $validated['allow_cross_platform_sessions'] = $request->boolean('allow_cross_platform_sessions');

        if ($validated['web_access'] && (int) $validated['max_web_sessions_per_user'] < 1) {
            throw ValidationException::withMessages([
                'max_web_sessions_per_user' => 'Un plan con acceso web debe permitir al menos un navegador por usuario.',
            ]);
        }

        if ($validated['mobile_access'] && (int) $validated['max_mobile_sessions_per_user'] < 1) {
            throw ValidationException::withMessages([
                'max_mobile_sessions_per_user' => 'Un plan con acceso movil debe permitir al menos un dispositivo por usuario.',
            ]);
        }

        if (! $validated['web_access']) {
            $validated['max_web_sessions_per_user'] = 0;
        }

        if (! $validated['mobile_access']) {
            $validated['max_mobile_sessions_per_user'] = 0;
        }

        return $validated;
    }

    public function syncStripe(Plan $plan)
    {
        try {
            $stripePlanSync = app(StripePlanSyncService::class);
            $stripePlanSync->sync($plan);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo sincronizar con Stripe: '.$exception->getMessage());
        }

        return back()->with('success', 'Plan sincronizado con Stripe correctamente.');
    }
}
