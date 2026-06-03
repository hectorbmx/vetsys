<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tenant;
use App\Models\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Mail\TenantUserInvitationMail;
use Illuminate\Support\Facades\Mail;

use App\Models\TenantSubscription;
use App\Models\TenantPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\User;

class TenantsController extends Controller
{
   public function index()
{
    $tenants = Tenant::with('plan')
        ->latest()
        ->paginate(10);

    $totalTenants = Tenant::count();
    $activeTenants = Tenant::where('status', 'active')->count();
    $inactiveTenants = Tenant::whereIn('status', ['inactive', 'suspended', 'cancelled'])->count();

    return view('admin.tenants.index', compact(
        'tenants',
        'totalTenants',
        'activeTenants',
        'inactiveTenants'
    ));
}
public function show(Tenant $tenant)
{
    $tenant->load([
        'plan',
        'users',
        'payments.plan',
        'subscriptions.plan',
    ]);

    $payments = $tenant->payments()
        ->with('plan')
        ->latest('paid_at')
        ->get();

    $plans = Plan::query()
        ->where('is_active', 1)
        ->orderBy('name')
        ->get();

    $pendingPlanRequest = $tenant->subscriptions
        ->where('status', 'pending')
        ->sortByDesc('created_at')
        ->first();

    return view('admin.tenants.show', compact(
        'tenant',
        'plans',
        'payments',
        'pendingPlanRequest'
    ));
}
   public function create()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('admin.tenants.create', compact('plans'));
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'plan_id' => ['required', 'exists:plans,id'],
            'status' => ['required', 'in:active,inactive,suspended,cancelled'],
        ]);

        $validated['slug'] = $validated['slug']
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $validated['is_active'] = $validated['status'] === 'active';
        $validated['created_by'] = auth()->id();

        Tenant::create($validated);

        return redirect()
            ->route('admin.tenants.index')
            ->with('success', 'Cliente creado correctamente.');
    }
 public function storeUser(Request $request, Tenant $tenant)
{
    // dd($request->all());
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],

        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email'),
        ],

        'role' => [
            'required',
            Rule::in(['client-admin', 'client-user']),
        ],
    ]);

    $token = Str::random(64);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => $validated['name'],
        'email' => $validated['email'],

        // Password temporal interno. No se comparte.
        'password' => Hash::make(Str::random(32)),

        // No puede entrar hasta aceptar invitación.
        'is_active' => false,

        'created_by' => auth()->id(),
        'invitation_token' => hash('sha256', $token),
        'invitation_expires_at' => now()->addDays(7),
    ]);

    $user->assignRole($validated['role']);

    // Por ahora probamos el link sin correo.
    $invitationUrl = route('invitation.accept', $token);
    Mail::to($user->email)->send(
        new TenantUserInvitationMail($user, $tenant, $invitationUrl)
    );

  return redirect()
    ->route('admin.tenants.show', $tenant)
    ->with('success', 'Usuario invitado correctamente. Se envió el enlace de activación por correo.');
}

public function assignPlan(Request $request, Tenant $tenant)
{
    $data = $request->validate([
        'plan_id' => ['required', 'exists:plans,id'],
        'starts_at' => ['required', 'date'],
        'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        'amount' => ['required', 'numeric', 'min:0'],
        'payment_method' => ['required', 'string', 'max:50'],
        'payment_reference' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string'],
    ]);

    DB::transaction(function () use ($tenant, $data) {
        $startsAt = Carbon::parse($data['starts_at'])->startOfDay();
        $endsAt = Carbon::parse($data['ends_at'])->endOfDay();
        $subscriptionStatus = $startsAt->isFuture() ? 'scheduled' : 'active';

        $pendingSubscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pendingSubscription) {
            $pendingSubscription->update([
                'plan_id' => $data['plan_id'],
                'provider' => 'manual',
                'status' => $subscriptionStatus,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => auth()->id(),
                'notes' => $data['notes'] ?? 'Solicitud atendida desde el panel admin al asignar plan.',
            ]);

            $subscription = $pendingSubscription;
        } else {
            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $data['plan_id'],
                'provider' => 'manual',
                'status' => $subscriptionStatus,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);
        }

        TenantPayment::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tenant_subscription_id' => $subscription->id,
            ],
            [
                'plan_id' => $data['plan_id'],
                'provider' => 'manual',
                'amount' => $data['amount'],
                'currency' => 'MXN',
                'status' => 'paid',
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'paid_at' => now(),
                'period_starts_at' => $startsAt,
                'period_ends_at' => $endsAt,
                'created_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        $tenantData = [
            'status' => 'active',
            'is_active' => true,
            'subscription_ends_at' => $endsAt,
        ];

        if ($subscriptionStatus === 'active') {
            $tenantData['plan_id'] = $data['plan_id'];
        }

        $tenant->update($tenantData);
    });

    return redirect()
        ->route('admin.tenants.show', $tenant)
        ->with('success', 'Plan asignado correctamente.');
}
}
