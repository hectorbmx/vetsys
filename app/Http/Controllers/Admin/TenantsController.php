<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tenant;
use App\Models\Plan;
use App\Mail\TenantActivationMail;
use App\Mail\TenantUserInvitationMail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use App\Models\TenantSubscription;
use App\Models\TenantPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use App\Services\StripeTenantCheckoutService;
use App\Models\TenantNotification;

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
        $request->merge([
            'slug' => Str::slug($request->input('slug') ?: $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('tenants', 'email'),
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,suspended,cancelled'],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $activationCode = (string) random_int(100000, 999999);
        $activationToken = Str::random(64);
        $activationUrl = route('activation.link', $activationToken);

        $validated['status'] = 'inactive';
        $validated['is_active'] = false;
        $validated['created_by'] = auth()->id();
        $validated['activation_code_token'] = Tenant::activationCodeHash($activationCode);
        $validated['activation_link_token'] = hash('sha256', $activationToken);
        $validated['activation_expires_at'] = now()->addDays(7);

        $tenant = Tenant::create($validated);
        $mailSent = $this->sendTenantActivationMail($tenant, $activationUrl, $activationCode);

        $this->flashTenantActivation($tenant, $activationCode, $activationUrl, $mailSent);

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('success', 'Cliente creado correctamente. Comparte el acceso de activacion.');
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
            Rule::in(['admin']),
        ],
    ]);

    $activationCode = (string) random_int(100000, 999999);
    $invitationToken = Str::random(64);
    $invitationUrl = route('invitation.accept', $invitationToken);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => $validated['name'],
        'email' => $validated['email'],

        // Password temporal interno. No se comparte.
        'password' => Hash::make(Str::random(32)),

        // No puede entrar hasta aceptar invitación.
        'is_active' => false,

        'created_by' => auth()->id(),
        'invitation_token' => User::activationCodeHash($activationCode),
        'invitation_link_token' => hash('sha256', $invitationToken),
        'invitation_expires_at' => now()->addDays(7),
    ]);

    $user->assignRole($validated['role']);

    $mailSent = $this->sendInvitationMail($user, $tenant, $invitationUrl, $activationCode);

    $activationExpiresAt = $user->invitation_expires_at->format('d/m/Y H:i');
    session()->flash('activation_code', $activationCode);
    session()->flash('activation_email', $user->email);
    session()->flash('activation_expires_at', $activationExpiresAt);
    session()->flash('activation_link', $invitationUrl);
    session()->flash(
        $mailSent ? 'activation_mail_sent' : 'activation_mail_failed',
        $mailSent
            ? 'Tambien enviamos el link de invitacion por correo.'
            : 'El usuario se creo, pero no se pudo enviar el correo. Puedes copiar el codigo o el link.'
    );

  return redirect()
    ->route('admin.tenants.show', $tenant)
    ->with('success', 'Usuario registrado correctamente.');
}

public function resendTenantActivationCode(Tenant $tenant)
{
    if (!$tenant->email) {
        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('error', 'El cliente no tiene email corporativo registrado.');
    }

    if ($tenant->activated_at || $tenant->users()->exists()) {
        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('error', 'Este tenant ya tiene una cuenta activa.');
    }

    if (User::where('email', $tenant->email)->exists()) {
        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('error', 'Ya existe un usuario con el email corporativo de este tenant.');
    }

    $activationCode = (string) random_int(100000, 999999);
    $activationToken = Str::random(64);
    $activationUrl = route('activation.link', $activationToken);

    $tenant->update([
        'status' => 'inactive',
        'is_active' => false,
        'activation_code_token' => Tenant::activationCodeHash($activationCode),
        'activation_link_token' => hash('sha256', $activationToken),
        'activation_expires_at' => now()->addDays(7),
        'activated_at' => null,
    ]);

    $mailSent = $this->sendTenantActivationMail($tenant, $activationUrl, $activationCode);
    $this->flashTenantActivation($tenant, $activationCode, $activationUrl, $mailSent);

    return redirect()
        ->route('admin.tenants.show', $tenant)
        ->with('success', 'Acceso de activacion del tenant generado correctamente.');
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
            'status' => $tenant->activated_at ? 'active' : 'inactive',
            'is_active' => (bool) $tenant->activated_at,
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

public function stripeCheckoutLink(Request $request, Tenant $tenant)
{
    $data = $request->validate([
        'plan_id' => [
            'required',
            Rule::exists('plans', 'id')->where(fn ($query) => $query->where('is_active', true)),
        ],
    ]);

    $plan = Plan::findOrFail($data['plan_id']);

    try {
        $session = app(StripeTenantCheckoutService::class)->createPlanCheckout(
            $tenant,
            $plan,
            route('login') . '?stripe_success=1',
            route('login') . '?stripe_cancel=1',
            auth()->id()
        );
    } catch (\Throwable $exception) {
        report($exception);

        return back()->with('error', 'No se pudo generar el link de Stripe: ' . $exception->getMessage());
    }

    TenantNotification::create([
        'tenant_id' => $tenant->id,
        'actor_user_id' => auth()->id(),
        'type' => 'saas_payment_link_created',
        'title' => 'Pago pendiente de SaaS',
        'body' => 'VetSys genero un link de pago Stripe para el plan ' . $plan->name . ' por $' . number_format((float) $plan->price, 2) . ' ' . ($plan->currency ?? 'MXN') . '.',
        'url' => $session->url,
        'data' => [
            'plan_id' => $plan->id,
            'stripe_checkout_session_id' => $session->id,
        ],
    ]);

    return back()
        ->with('success', 'Link de Stripe generado correctamente. Puedes copiarlo y enviarlo al tenant.')
        ->with('stripe_checkout_link', $session->url);
}

public function destroyPayment(Tenant $tenant, TenantPayment $payment)
{
    if ((int) $payment->tenant_id !== (int) $tenant->id) {
        abort(404);
    }

    if ($payment->status !== 'cancelled') {
        return back()->with('error', 'Solo se pueden eliminar pagos con estatus cancelado.');
    }

    $payment->delete();

    return back()->with('success', 'El registro de pago ha sido eliminado.');
}

public function clearCancelledPayments(Tenant $tenant)
{
    $count = $tenant->payments()
        ->where('status', 'cancelled')
        ->delete();

    return back()->with('success', "Se han eliminado {$count} registros de pago cancelados.");
}

public function resendActivationCode(Tenant $tenant, User $user)
{
    if ((int) $user->tenant_id !== (int) $tenant->id) {
        abort(404);
    }

    if ($user->invitation_accepted_at) {
        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('error', 'Este usuario ya activo su cuenta.');
    }

    $activationCode = (string) random_int(100000, 999999);
    $invitationToken = Str::random(64);
    $invitationUrl = route('invitation.accept', $invitationToken);

    $user->update([
        'is_active' => false,
        'invitation_token' => User::activationCodeHash($activationCode),
        'invitation_link_token' => hash('sha256', $invitationToken),
        'invitation_expires_at' => now()->addDays(7),
        'invitation_accepted_at' => null,
    ]);

    $mailSent = $this->sendInvitationMail($user, $tenant, $invitationUrl, $activationCode);

    $activationExpiresAt = $user->invitation_expires_at->format('d/m/Y H:i');
    session()->flash('activation_code', $activationCode);
    session()->flash('activation_email', $user->email);
    session()->flash('activation_expires_at', $activationExpiresAt);
    session()->flash('activation_link', $invitationUrl);
    session()->flash(
        $mailSent ? 'activation_mail_sent' : 'activation_mail_failed',
        $mailSent
            ? 'Tambien reenviamos el link de invitacion por correo.'
            : 'No se pudo enviar el correo. Puedes copiar el codigo o el link.'
    );

    return redirect()
        ->route('admin.tenants.show', $tenant)
        ->with('success', 'Acceso de activacion regenerado correctamente.');
}

private function sendInvitationMail(User $user, Tenant $tenant, string $invitationUrl, string $activationCode): bool
{
    try {
        Mail::to($user->email)->send(
            new TenantUserInvitationMail($user, $tenant, $invitationUrl, $activationCode)
        );

        return true;
    } catch (\Throwable $exception) {
        return false;
    }
}

private function sendTenantActivationMail(Tenant $tenant, string $activationUrl, string $activationCode): bool
{
    try {
        Mail::to($tenant->email)->send(
            new TenantActivationMail($tenant, $activationUrl, $activationCode)
        );

        return true;
    } catch (\Throwable $exception) {
        return false;
    }
}

private function flashTenantActivation(Tenant $tenant, string $activationCode, string $activationUrl, bool $mailSent): void
{
    session()->flash('activation_code', $activationCode);
    session()->flash('activation_email', $tenant->email);
    session()->flash('activation_expires_at', $tenant->activation_expires_at?->format('d/m/Y H:i'));
    session()->flash('activation_link', $activationUrl);
    session()->flash(
        $mailSent ? 'activation_mail_sent' : 'activation_mail_failed',
        $mailSent
            ? 'Tambien enviamos el link de activacion por correo.'
            : 'No se pudo enviar el correo. Puedes copiar el codigo o el link.'
    );
}
}
