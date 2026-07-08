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
use App\Rules\GloballyUniqueEmail;

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
    $billingSummary = $this->tenantBillingSummary($tenant);

    return view('admin.tenants.show', compact(
        'tenant',
        'plans',
        'payments',
        'pendingPlanRequest',
        'billingSummary'
    ));
}
   public function update(Request $request, Tenant $tenant)
   {
       $validated = $request->validate([
           'name' => [Rule::requiredIf($request->isMethod('PUT')), 'string', 'max:255'],
           'slug' => [Rule::requiredIf($request->isMethod('PUT')), 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
           'business_name' => ['nullable', 'string', 'max:255'],
           'email' => [
               'required',
               'email',
               'max:255',
               Rule::unique('tenants', 'email')->ignore($tenant->id),
               new GloballyUniqueEmail('tenants', $tenant->id),
           ],
           'phone' => ['nullable', 'string', 'max:50'],
           'status' => ['nullable', 'in:active,inactive,suspended,cancelled'],
           'plan_id' => ['nullable', 'exists:plans,id'],
       ]);

       if (array_key_exists('status', $validated)) {
           $validated['is_active'] = $validated['status'] === 'active';
       }

       $tenant->update($validated);

       return redirect()
           ->route('admin.tenants.show', $tenant)
           ->with('success', 'Cliente actualizado correctamente.');
   }

   public function edit(Tenant $tenant)
   {
       $plans = Plan::where('is_active', true)
           ->orderBy('sort_order')
           ->get();

       return view('admin.tenants.edit', compact('tenant', 'plans'));
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
                new GloballyUniqueEmail,
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,suspended,cancelled'],
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_action' => ['required', Rule::in(['trial', 'paid', 'pending'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'card_manual', 'manual', 'other'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
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

        $billingData = collect($validated)->only([
            'billing_action',
            'starts_at',
            'ends_at',
            'amount',
            'payment_method',
            'payment_reference',
            'notes',
        ])->all();

        $tenantData = collect($validated)->except([
            'billing_action',
            'starts_at',
            'ends_at',
            'amount',
            'payment_method',
            'payment_reference',
            'notes',
        ])->all();

        $tenant = DB::transaction(function () use ($tenantData, $billingData) {
            $tenant = Tenant::create($tenantData);
            $this->createManualBillingRecord($tenant, $billingData);

            return $tenant;
        });

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
            new GloballyUniqueEmail,
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

public function updateUser(Request $request, Tenant $tenant, User $user, int $tenantId = null, int $userId = null)
{
    $tenant = $this->resolveTenantRouteModel($tenant, $tenantId);
    $user = $this->resolveUserRouteModel($user, $userId);
    $this->ensureTenantUser($tenant, $user);

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'email',
            'max:255',
            new GloballyUniqueEmail('users', $user->id),
        ],
    ]);

    $user->update($validated);

    return redirect()
        ->route('admin.tenants.show', $tenant)
        ->with('success', 'Usuario actualizado correctamente.');
}

public function destroyUser(Tenant $tenant, User $user, int $tenantId = null, int $userId = null)
{
    $tenant = $this->resolveTenantRouteModel($tenant, $tenantId);
    $user = $this->resolveUserRouteModel($user, $userId);
    $this->ensureTenantUser($tenant, $user);

    $user->delete();

    return redirect()
        ->route('admin.tenants.show', $tenant)
        ->with('success', 'Usuario eliminado correctamente.');
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
        'billing_action' => ['required', Rule::in(['trial', 'paid', 'pending'])],
        'starts_at' => ['required', 'date'],
        'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        'amount' => ['nullable', 'numeric', 'min:0'],
        'payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'card_manual', 'manual', 'other'])],
        'payment_reference' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string'],
    ]);

    DB::transaction(function () use ($tenant, $data) {
        $this->cancelPendingManualBilling($tenant);
        $this->createManualBillingRecord($tenant, $data);
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

    // return back()
    //     ->with('success', 'Link de Stripe generado correctamente. Puedes copiarlo y enviarlo al tenant.')
    //     ->with('stripe_checkout_link', $session->url);
    return redirect()
    ->route('admin.tenants.show', $tenant)
    ->with('success', 'Link de Stripe generado correctamente.')
    ->with('stripe_checkout_link', $session->url);
}

private function createManualBillingRecord(Tenant $tenant, array $data): TenantSubscription
{
    $plan = Plan::findOrFail($data['plan_id']);
    $startsAt = Carbon::parse($data['starts_at'])->startOfDay();
    $endsAt = Carbon::parse($data['ends_at'])->endOfDay();
    $billingAction = $data['billing_action'];
    $isTrial = $billingAction === 'trial';
    $isPaid = in_array($billingAction, ['trial', 'paid'], true);
    $subscriptionStatus = $isPaid
        ? ($startsAt->isFuture() ? 'scheduled' : 'active')
        : 'pending';
    $amount = $isTrial ? 0 : (float) ($data['amount'] ?? $plan->price ?? 0);
    $paymentMethod = $isTrial
        ? 'trial'
        : ($data['payment_method'] ?? ($isPaid ? 'manual' : 'manual_pending'));
    $note = $data['notes'] ?? match ($billingAction) {
        'trial' => 'Trial otorgado desde master.',
        'paid' => 'Pago manual registrado desde master.',
        default => 'Pago pendiente registrado desde master.',
    };

    $subscription = TenantSubscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'provider' => 'manual',
        'status' => $subscriptionStatus,
        'starts_at' => $startsAt,
        'trial_ends_at' => $isTrial ? $endsAt : null,
        'ends_at' => $endsAt,
        'created_by' => auth()->id(),
        'notes' => $note,
    ]);

    TenantPayment::create([
        'tenant_id' => $tenant->id,
        'tenant_subscription_id' => $subscription->id,
        'plan_id' => $plan->id,
        'provider' => 'manual',
        'amount' => $amount,
        'currency' => $plan->currency ?? 'MXN',
        'status' => $isPaid ? 'paid' : 'pending',
        'payment_method' => $paymentMethod,
        'payment_reference' => $data['payment_reference'] ?? null,
        'paid_at' => $isPaid ? now() : null,
        'period_starts_at' => $startsAt,
        'period_ends_at' => $endsAt,
        'created_by' => auth()->id(),
        'notes' => $note,
    ]);

    $tenantData = [
        'plan_id' => $plan->id,
        'trial_ends_at' => $isTrial ? $endsAt : null,
        'subscription_ends_at' => $isPaid ? $endsAt : null,
    ];

    if ($isPaid) {
        $tenantData['status'] = $tenant->activated_at ? 'active' : $tenant->status;
        $tenantData['is_active'] = $tenant->activated_at ? true : $tenant->is_active;
    }

    $tenant->update($tenantData);

    return $subscription;
}

private function cancelPendingManualBilling(Tenant $tenant): void
{
    TenantPayment::where('tenant_id', $tenant->id)
        ->where('status', 'pending')
        ->where('provider', 'manual')
        ->update(['status' => 'cancelled']);

    TenantSubscription::where('tenant_id', $tenant->id)
        ->where('status', 'pending')
        ->where('provider', 'manual')
        ->update(['status' => 'cancelled']);
}

private function tenantBillingSummary(Tenant $tenant): array
{
    $subscriptions = $tenant->subscriptions;
    $payments = $tenant->payments;
    $activeSubscription = $subscriptions
        ->where('status', 'active')
        ->filter(fn ($subscription) => ! $subscription->ends_at || $subscription->ends_at->isFuture())
        ->sortByDesc(fn ($subscription) => $subscription->starts_at ?? $subscription->created_at)
        ->first();
    $pendingPayment = $payments
        ->where('status', 'pending')
        ->sortByDesc('created_at')
        ->first();
    $paidPayment = $payments
        ->where('status', 'paid')
        ->filter(fn ($payment) => ! $payment->period_ends_at || $payment->period_ends_at->isFuture())
        ->sortByDesc(fn ($payment) => $payment->period_ends_at ?? $payment->paid_at ?? $payment->created_at)
        ->first();

    if ($tenant->status !== 'active' || ! $tenant->is_active) {
        $description = $tenant->status !== 'active'
            ? 'El estado administrativo no permite operar.'
            : 'El estado visible es activo, pero el acceso operativo esta apagado.';

        return [
            'status' => 'admin_blocked',
            'label' => 'Bloqueo administrativo',
            'description' => $description,
            'badge' => 'bg-slate-100 text-slate-600',
            'ends_at' => null,
            'active_subscription' => $activeSubscription,
            'payment' => $paidPayment ?? $pendingPayment,
        ];
    }

    if ($activeSubscription && $paidPayment) {
        $isTrial = $paidPayment->payment_method === 'trial' && (float) $paidPayment->amount === 0.0;

        return [
            'status' => $isTrial ? 'trial_active' : 'paid_active',
            'label' => $isTrial ? 'Trial vigente' : 'Suscripcion activa',
            'description' => $isTrial ? 'Acceso sin cargo hasta la fecha indicada.' : 'Pago vigente registrado.',
            'badge' => $isTrial ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700',
            'ends_at' => $activeSubscription->ends_at ?? $paidPayment->period_ends_at,
            'active_subscription' => $activeSubscription,
            'payment' => $paidPayment,
        ];
    }

    if ($pendingPayment || $pendingPlanRequest = $subscriptions->where('status', 'pending')->sortByDesc('created_at')->first()) {
        return [
            'status' => 'pending_payment',
            'label' => 'Pago pendiente',
            'description' => 'El tenant solo tendra facturacion limitada hasta confirmar pago.',
            'badge' => 'bg-amber-50 text-amber-700',
            'ends_at' => $pendingPayment?->period_ends_at ?? $pendingPlanRequest?->ends_at,
            'active_subscription' => null,
            'payment' => $pendingPayment,
        ];
    }

    if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast()) {
        return [
            'status' => 'expired',
            'label' => 'Vencido',
            'description' => 'La vigencia registrada ya termino.',
            'badge' => 'bg-rose-50 text-rose-700',
            'ends_at' => $tenant->subscription_ends_at,
            'active_subscription' => null,
            'payment' => null,
        ];
    }

    return [
        'status' => 'missing_contract',
        'label' => 'Sin contrato registrado',
        'description' => 'Tiene plan asignado, pero falta suscripcion y pago/intencion.',
        'badge' => 'bg-rose-50 text-rose-700',
        'ends_at' => $tenant->subscription_ends_at,
        'active_subscription' => null,
        'payment' => null,
    ];
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
    $this->ensureTenantUser($tenant, $user);

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

private function ensureTenantUser(Tenant $tenant, User $user): void
{
    abort_if((int) $user->tenant_id !== (int) $tenant->id, 404);
}

private function resolveTenantRouteModel(Tenant $tenant, ?int $tenantId): Tenant
{
    return $tenant->exists ? $tenant : Tenant::findOrFail($tenantId);
}

private function resolveUserRouteModel(User $user, ?int $userId): User
{
    return $user->exists ? $user : User::findOrFail($userId);
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
