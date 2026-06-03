<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\StripePlanSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Tenant;


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
    public function store(Request $request)
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'slug' => ['nullable', 'string', 'max:255', 'unique:plans,slug'],
        'description' => ['nullable', 'string'],

        'price' => ['required', 'numeric', 'min:0'],
        'currency' => ['required', 'string', 'max:3'],

        'billing_period' => [
            'required',
            'in:monthly,yearly,one_time,free'
        ],

        'max_users' => ['nullable', 'integer', 'min:1'],
        'max_clients' => ['nullable', 'integer', 'min:1'],
        'trial_days' => ['nullable', 'integer', 'min:0'],

        'stripe_product_id' => ['nullable', 'string'],
        'stripe_price_id' => ['nullable', 'string'],
    ]);

    $validated['slug'] = $validated['slug']
        ? Str::slug($validated['slug'])
        : Str::slug($validated['name']);

    $validated['is_active'] = $request->boolean('is_active');

    Plan::create($validated);

    return redirect()
        ->route('admin.planes.index')
        ->with('success', 'Plan creado correctamente.');
}

    public function syncStripe(Plan $plan)
    {
        try {
            $stripePlanSync = app(StripePlanSyncService::class);
            $stripePlanSync->sync($plan);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo sincronizar con Stripe: ' . $exception->getMessage());
        }

        return back()->with('success', 'Plan sincronizado con Stripe correctamente.');
    }
}
