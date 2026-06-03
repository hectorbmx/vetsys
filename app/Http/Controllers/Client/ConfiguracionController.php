<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\TenantUserInvitationMail;
use Illuminate\Http\Request;

use App\Models\AnimalType;
use App\Models\AnimalTypeField;
use App\Models\Plan;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Exception;
use Spatie\Permission\Models\Role;

class ConfiguracionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 public function index()
{
    // TEMPORAL: Pon esto al inicio para validar si Laravel ya entra aquí
    // dd("¡ESTOY AQUÍ!");

    $user = auth()->user();
    $tenantId = $user->tenant_id;
    $tenant = $user->tenant()->with('plan')->first();

    $animalTypes = AnimalType::where('tenant_id', $tenantId)
        ->latest()
        ->get();

    $teamUsers = User::where('tenant_id', $tenantId)
        ->with('roles')
        ->latest()
        ->get();

    $activePlans = Plan::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('price')
        ->get();

    $subscriptionPayments = TenantPayment::query()
        ->where('tenant_id', $tenantId)
        ->with('plan')
        ->latest('created_at')
        ->get();

    $pendingPlanRequest = TenantSubscription::query()
        ->where('tenant_id', $tenantId)
        ->where('status', 'pending')
        ->with('plan')
        ->latest()
        ->first();

    $pendingPlanPayment = $pendingPlanRequest
        ? TenantPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_subscription_id', $pendingPlanRequest->id)
            ->latest()
            ->first()
        : null;

    $maxUsers = $tenant?->plan?->max_users;
    $usersUsed = $teamUsers->count();
    $canInviteUsers = is_null($maxUsers) || $usersUsed < (int) $maxUsers;
    $roleOptions = [
        'client-admin' => 'Administrador',
        'client-user' => 'Usuario operativo',
    ];

    return view('client.mi-configuracion.index', compact(
        'animalTypes',
        'tenant',
        'teamUsers',
        'maxUsers',
        'usersUsed',
        'canInviteUsers',
        'roleOptions',
        'activePlans',
        'subscriptionPayments',
        'pendingPlanRequest',
        'pendingPlanPayment'
    ));
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['tenant_id'] = $tenantId;
        $data['slug']      = Str::slug($request->name);
        $data['is_active'] = true;

        try {
            AnimalType::create($data);

            // CORRECCIÓN AQUÍ: Apuntar al nuevo nombre de la ruta
            return redirect()
                ->route('client.mi-configuracion.index')
                ->with('success', '¡Tipo de animal agregado correctamente!');
        } catch (Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Hubo un problema al guardar la configuración.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function fieldsIndex(AnimalType $animalType)
    {
        // Seguridad: Validamos que pertenezca al mismo Tenant
        if ($animalType->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'No autorizado.');
        }

        // Cargamos los campos que ya existen para esta especie
        $fields = $animalType->fields()->orderBy('sort_order')->get();

        // return view('client.mi-configuracion.fields', compact('animalType', 'fields'));
        return view('client.mi-configuracion.fields.index', compact('animalType', 'fields'));
    }
public function fieldsStore(Request $request, AnimalType $animalType)
{
    if ($animalType->tenant_id !== auth()->user()->tenant_id) {
        abort(403);
    }

    $data = $request->validate([
        'label'       => ['required', 'string', 'max:255'],
        'field_type'  => ['required', 'in:text,textarea,number,decimal,date,datetime,checkbox,select,boolean'],
        'help_text'   => ['nullable', 'string', 'max:255'],
    ]);

    $data['tenant_id']   = auth()->user()->tenant_id;
    $data['slug']        = Str::slug($request->label);
    $data['is_required'] = $request->has('is_required');
    $data['is_active']   = true;
    $data['sort_order']  = $animalType->fields()->count() + 1;

    try {
        $animalType->fields()->create($data);

        return redirect()
            ->route('animal-types.fields.index', $animalType)
            ->with('success', '¡Campo personalizado agregado con éxito!');
    } catch (\Exception $e) {
        return redirect()
            ->back()
            ->withInput()
            ->with('error', 'Hubo un error al crear el campo.');
    }
}

public function storeUser(Request $request)
{
    $tenant = auth()->user()->tenant()->with('plan')->firstOrFail();
    $usersUsed = $tenant->users()->count();
    $maxUsers = $tenant->plan?->max_users;

    if (!is_null($maxUsers) && $usersUsed >= (int) $maxUsers) {
        return back()
            ->with('activeTab', 'usuarios')
            ->with('error', 'Tu plan actual ya alcanzÃ³ el lÃ­mite de usuarios. Mejora tu plan para invitar mÃ¡s equipo.');
    }

    $validator = Validator::make($request->all(), [
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

    if ($validator->fails()) {
        return back()
            ->withErrors($validator)
            ->withInput()
            ->with('activeTab', 'usuarios');
    }

    $validated = $validator->validated();

    $token = Str::random(64);

    try {
        foreach (['client-admin', 'client-user'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        DB::transaction(function () use ($tenant, $validated, $token) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(32)),
                'is_active' => false,
                'created_by' => auth()->id(),
                'invitation_token' => hash('sha256', $token),
                'invitation_expires_at' => now()->addDays(7),
            ]);

            $user->assignRole($validated['role']);

            Mail::to($user->email)->send(
                new TenantUserInvitationMail($user, $tenant, route('invitation.accept', $token))
            );
        });

        return redirect()
            ->route('client.mi-configuracion.index')
            ->with('activeTab', 'usuarios')
            ->with('success', 'Usuario invitado correctamente. Se enviÃ³ el enlace de activaciÃ³n por correo.');
    } catch (\Throwable $exception) {
        report($exception);

        return back()
            ->withInput()
            ->with('activeTab', 'usuarios')
            ->with('error', 'No se pudo invitar al usuario. Revisa la configuraciÃ³n de correo o intenta de nuevo.');
    }
}

public function requestPlanChange(Request $request)
{
    $tenant = auth()->user()->tenant()->with('plan')->firstOrFail();

    $data = $request->validate([
        'plan_id' => [
            'required',
            Rule::exists('plans', 'id')->where(fn ($query) => $query->where('is_active', true)),
        ],
        'payment_method' => ['required', Rule::in(['card_manual', 'transfer', 'cash', 'other'])],
        'payment_reference' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ]);

    $plan = Plan::findOrFail($data['plan_id']);
    $startsAt = $this->nextSubscriptionStart($tenant);
    $endsAt = $this->nextSubscriptionEnd($startsAt, $plan->billing_period);

    DB::transaction(function () use ($tenant, $plan, $data, $startsAt, $endsAt) {
        $note = trim('Renovacion programada solicitada desde el panel del cliente. ' . ($data['notes'] ?? ''));

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'provider' => 'manual',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => auth()->id(),
                'notes' => $note,
            ]);
        } else {
            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'provider' => 'manual',
                'status' => 'pending',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => auth()->id(),
                'notes' => $note,
            ]);
        }

        TenantPayment::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tenant_subscription_id' => $subscription->id,
                'status' => 'pending',
            ],
            [
                'plan_id' => $plan->id,
                'provider' => 'manual',
                'amount' => $plan->price,
                'currency' => $plan->currency ?? 'MXN',
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'period_starts_at' => $startsAt,
                'period_ends_at' => $endsAt,
                'created_by' => auth()->id(),
                'notes' => $note,
            ]
        );
    });

    return back()
        ->with('activeTab', 'facturacion')
        ->with('success', 'Renovacion solicitada. El plan quedara pendiente hasta que el admin confirme el pago manual.');
}

private function nextSubscriptionStart($tenant): Carbon
{
    if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isFuture()) {
        return $tenant->subscription_ends_at->copy()->addDay()->startOfDay();
    }

    return now()->startOfDay();
}

private function nextSubscriptionEnd(Carbon $startsAt, ?string $billingPeriod): ?Carbon
{
    return match ($billingPeriod) {
        'monthly' => $startsAt->copy()->addMonth()->subDay()->endOfDay(),
        'yearly' => $startsAt->copy()->addYear()->subDay()->endOfDay(),
        default => null,
    };
}

}
