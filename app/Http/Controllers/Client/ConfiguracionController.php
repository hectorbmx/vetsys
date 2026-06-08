<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\TenantUserInvitationMail;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


use App\Models\AnimalType;
use App\Models\AnimalTypeField;
use App\Models\Animal;
use App\Models\CatalogItem;
use App\Models\Club;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PriceHistory;
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
use Illuminate\Validation\ValidationException;
use Exception;
use Spatie\Permission\Models\Role;
use App\Services\StripeTenantCheckoutService;
use App\Models\TenantBillingProfile;

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
    $billingProfile = $tenant?->billingProfile;
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
    $roleOptions = $this->tenantRoleOptions();
    $roleDescriptions = $this->tenantRoleDescriptions();
    $canManageTeam = $user->hasRole('admin');

    $this->ensureTenantRolesExist();

    return view('client.mi-configuracion.index', compact(
        'animalTypes',
        'tenant',
        'billingProfile',
        'teamUsers',
        'maxUsers',
        'usersUsed',
        'canInviteUsers',
        'roleOptions',
        'roleDescriptions',
        'canManageTeam',
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
    abort_unless(auth()->user()->hasRole('admin'), 403);

    $tenant = auth()->user()->tenant()->with('plan')->firstOrFail();
    $usersUsed = $tenant->users()->count();
    $maxUsers = $tenant->plan?->max_users;
    $roleOptions = $this->tenantRoleOptions();

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
            Rule::in(array_keys($roleOptions)),
        ],
    ]);

    if ($validator->fails()) {
        return back()
            ->withErrors($validator)
            ->withInput()
            ->with('activeTab', 'usuarios');
    }

    $validated = $validator->validated();

    $activationCode = (string) random_int(100000, 999999);
    $token = Str::random(64);

    try {
        $this->ensureTenantRolesExist();

        DB::transaction(function () use ($tenant, $validated, $token, $activationCode) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(32)),
                'is_active' => false,
                'created_by' => auth()->id(),
                'invitation_token' => User::activationCodeHash($activationCode),
                'invitation_link_token' => hash('sha256', $token),
                'invitation_expires_at' => now()->addDays(7),
            ]);

            $user->assignRole($validated['role']);

            Mail::to($user->email)->send(
                new TenantUserInvitationMail($user, $tenant, route('invitation.accept', $token), $activationCode)
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

private function tenantRoleOptions(): array
{
    return [
        'admin' => 'Administrador',
        'asistente' => 'Asistente',
        'cajero' => 'Cajero',
    ];
}

private function tenantRoleDescriptions(): array
{
    return [
        'admin' => 'Administra usuarios, configuracion y operacion del tenant.',
        'asistente' => 'Apoya la operacion clinica y la captura diaria del tenant.',
        'cajero' => 'Atiende ventas, cobros y movimientos de caja del tenant.',
    ];
}

private function ensureTenantRolesExist(): void
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (array_keys($this->tenantRoleOptions()) as $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    }

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
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

public function importCustomers(Request $request)
{
    $tenantId = auth()->user()->tenant_id;

    $request->validate([
        'customers_csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
    ]);

    $file = $request->file('customers_csv');
    $handle = fopen($file->getRealPath(), 'r');

    if ($handle === false) {
        throw ValidationException::withMessages([
            'customers_csv' => 'No se pudo leer el archivo CSV.',
        ]);
    }

    $firstLine = fgets($handle);

    if ($firstLine === false) {
        fclose($handle);

        return back()
            ->with('activeTab', 'importar')
            ->with('error', 'El CSV esta vacio.');
    }

    $delimiter = $this->detectCsvDelimiter($firstLine);
    $headers = $this->normalizeCsvHeaders(str_getcsv($firstLine, $delimiter));
    $requiredHeaders = ['clienteid', 'nombre', 'ap', 'am', 'correo', 'telefono', 'created_at', 'estatus'];
    $missingHeaders = array_diff($requiredHeaders, $headers);

    if (!empty($missingHeaders)) {
        fclose($handle);

        return back()
            ->with('activeTab', 'importar')
            ->with('error', 'Faltan columnas requeridas en el CSV: ' . implode(', ', $missingHeaders) . '.');
    }

    $created = 0;
    $skipped = 0;
    $errors = [];
    $rowNumber = 1;

    DB::transaction(function () use ($handle, $delimiter, $headers, $tenantId, &$created, &$skipped, &$errors, &$rowNumber) {
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $data = $this->combineCsvRow($headers, $row);
            $legacyId = trim((string) ($data['clienteid'] ?? ''));
            $name = trim((string) ($data['nombre'] ?? ''));
            $lastName = trim(implode(' ', array_filter([
                trim((string) ($data['ap'] ?? '')),
                trim((string) ($data['am'] ?? '')),
            ])));
            $email = trim((string) ($data['correo'] ?? ''));
            $phone = $this->normalizePhone((string) ($data['telefono'] ?? ''));

            if ($name === '') {
                $errors[] = "Fila {$rowNumber}: nombre vacio.";
                continue;
            }

            $existingQuery = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('notes', 'like', "%Legacy ClienteID: {$legacyId}%");

            if ($legacyId !== '' && $existingQuery->exists()) {
                $skipped++;
                continue;
            }

            $createdAt = $this->parseLegacyDate($data['created_at'] ?? null);

            $customer = new Customer([
                'tenant_id' => $tenantId,
                'name' => $name,
                'last_name' => $lastName !== '' ? $lastName : null,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'status' => ((string) ($data['estatus'] ?? '1')) === '0' ? 'inactive' : 'active',
                'notes' => trim('Importado desde legacy. Legacy ClienteID: ' . ($legacyId !== '' ? $legacyId : 'sin-id')),
            ]);
            $customer->created_at = $createdAt ?? now();
            $customer->updated_at = now();
            $customer->save();

            $created++;
        }
    });

    fclose($handle);

    $message = "Importacion terminada. Creados: {$created}. Omitidos: {$skipped}.";

    if (!empty($errors)) {
        $message .= ' Errores: ' . implode(' ', array_slice($errors, 0, 5));
    }

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'importar')
        ->with($created > 0 ? 'success' : 'error', $message);
}

public function importServices(Request $request)
{
    $tenantId = auth()->user()->tenant_id;

    $request->validate([
        'services_csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
    ]);

    $file = $request->file('services_csv');
    $handle = fopen($file->getRealPath(), 'r');

    if ($handle === false) {
        throw ValidationException::withMessages([
            'services_csv' => 'No se pudo leer el archivo CSV.',
        ]);
    }

    $firstLine = fgets($handle);

    if ($firstLine === false) {
        fclose($handle);

        return back()
            ->with('activeTab', 'importar')
            ->with('error', 'El CSV de servicios esta vacio.');
    }

    $delimiter = $this->detectCsvDelimiter($firstLine);
    $headers = $this->normalizeCsvHeaders(str_getcsv($firstLine, $delimiter));
    $requiredHeaders = ['servid', 'sctype', 'precio', 'estatus', 'created_at'];
    $missingHeaders = array_diff($requiredHeaders, $headers);

    if (!empty($missingHeaders)) {
        fclose($handle);

        return back()
            ->with('activeTab', 'importar')
            ->with('error', 'Faltan columnas requeridas en el CSV de servicios: ' . implode(', ', $missingHeaders) . '.');
    }

    $created = 0;
    $skipped = 0;
    $errors = [];
    $rowNumber = 1;

    DB::transaction(function () use ($handle, $delimiter, $headers, $tenantId, &$created, &$skipped, &$errors, &$rowNumber) {
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $data = $this->combineCsvRow($headers, $row);
            $legacyId = trim((string) ($data['servid'] ?? ''));
            $name = trim((string) ($data['sctype'] ?? ''));
            $price = $this->normalizeMoney($data['precio'] ?? 0);

            if ($name === '') {
                $errors[] = "Fila {$rowNumber}: nombre de servicio vacio.";
                continue;
            }

            if ($legacyId !== '' && CatalogItem::query()
                ->where('tenant_id', $tenantId)
                ->where('type', 'service')
                ->where('description', 'like', "%Legacy ServID: {$legacyId}%")
                ->exists()) {
                $skipped++;
                continue;
            }

            $createdAt = $this->parseLegacyDate($data['created_at'] ?? null);

            $item = new CatalogItem([
                'tenant_id' => $tenantId,
                'name' => $name,
                'sku' => null,
                'type' => 'service',
                'description' => trim('Importado desde legacy. Legacy ServID: ' . ($legacyId !== '' ? $legacyId : 'sin-id')),
                'tax_percentage' => 0.00,
                'has_inventory' => false,
                'is_active' => ((string) ($data['estatus'] ?? '1')) !== '0',
            ]);
            $item->created_at = $createdAt ?? now();
            $item->updated_at = now();
            $item->save();

            PriceHistory::create([
                'tenant_id' => $tenantId,
                'catalog_item_id' => $item->id,
                'price' => $price,
                'start_date' => $createdAt ?? now(),
                'end_date' => null,
            ]);

            $created++;
        }
    });

    fclose($handle);

    $message = "Importacion de servicios terminada. Creados: {$created}. Omitidos: {$skipped}.";

    if (!empty($errors)) {
        $message .= ' Errores: ' . implode(' ', array_slice($errors, 0, 5));
    }

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'importar')
        ->with($created > 0 ? 'success' : 'error', $message);
}

public function importHorses(Request $request)
{
    $tenantId = auth()->user()->tenant_id;
    $animalTypeId = $this->resolveHorseAnimalTypeId($tenantId);

    $request->validate([
        'horses_csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
    ]);

    $file = $request->file('horses_csv');
    $handle = fopen($file->getRealPath(), 'r');

    if ($handle === false) {
        throw ValidationException::withMessages([
            'horses_csv' => 'No se pudo leer el archivo CSV.',
        ]);
    }

    $firstLine = fgets($handle);

    if ($firstLine === false) {
        fclose($handle);

        return back()
            ->with('activeTab', 'importar')
            ->with('error', 'El CSV de caballos esta vacio.');
    }

    $delimiter = $this->detectCsvDelimiter($firstLine);
    $headers = $this->normalizeCsvHeaders(str_getcsv($firstLine, $delimiter));
    $requiredHeaders = [
        'caballoid',
        'clienteid',
        'nombre',
        'fnacimiento',
        'color',
        'sexo',
        'raza',
        'clubid',
        'microchip',
        'estatus',
        'fechanac',
        'fotochip',
        'fecharegistro',
    ];
    $missingHeaders = array_diff($requiredHeaders, $headers);

    if (!empty($missingHeaders)) {
        fclose($handle);

        return back()
            ->with('activeTab', 'importar')
            ->with('error', 'Faltan columnas requeridas en el CSV de caballos: ' . implode(', ', $missingHeaders) . '.');
    }

    $created = 0;
    $skipped = 0;
    $errors = [];
    $rowNumber = 1;

    DB::transaction(function () use ($handle, $delimiter, $headers, $tenantId, $animalTypeId, &$created, &$skipped, &$errors, &$rowNumber) {
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $data = $this->combineCsvRow($headers, $row);
            $legacyHorseId = trim((string) ($data['caballoid'] ?? ''));
            $legacyCustomerId = trim((string) ($data['clienteid'] ?? ''));
            $name = trim((string) ($data['nombre'] ?? ''));

            if ($name === '') {
                $errors[] = "Fila {$rowNumber}: nombre de caballo vacio.";
                continue;
            }

            if ($legacyHorseId !== '' && Animal::query()
                ->where('tenant_id', $tenantId)
                ->where('animal_type_id', $animalTypeId)
                ->where('notes', 'like', "%Legacy CaballoID: {$legacyHorseId}%")
                ->exists()) {
                $skipped++;
                continue;
            }

            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('notes', 'like', "%Legacy ClienteID: {$legacyCustomerId}%")
                ->first();

            if (!$customer) {
                $errors[] = "Fila {$rowNumber}: no se encontro cliente legacy {$legacyCustomerId}.";
                continue;
            }

            $birthdate = $this->parseLegacyDate($data['fechanac'] ?? null)
                ?? $this->parseLegacyDate($data['fnacimiento'] ?? null);
            $createdAt = $this->parseLegacyDate($data['fecharegistro'] ?? null);
            $status = ((string) ($data['estatus'] ?? '1')) === '0' ? 'inactive' : 'active';
            $microchip = trim((string) ($data['microchip'] ?? ''));
            $club = $this->resolveLegacyClub($tenantId, $data['clubid'] ?? null);

            $animal = new Animal([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'club_id' => $club?->id,
                'animal_type_id' => $animalTypeId,
                'name' => $name,
                'sex' => $this->normalizeLegacySex($data['sexo'] ?? null),
                'birthdate' => $birthdate,
                'color' => trim((string) ($data['color'] ?? '')) ?: null,
                'microchip' => $microchip !== '' ? $microchip : null,
                'status' => $status,
                'notes' => $this->buildHorseLegacyNotes($data, $legacyHorseId, $legacyCustomerId),
            ]);
            $animal->created_at = $createdAt ?? now();
            $animal->updated_at = now();
            $animal->save();

            $created++;
        }
    });

    fclose($handle);

    $message = "Importacion de caballos terminada. Creados: {$created}. Omitidos: {$skipped}.";

    if (!empty($errors)) {
        $message .= ' Errores: ' . implode(' ', array_slice($errors, 0, 5));
    }

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'importar')
        ->with($created > 0 ? 'success' : 'error', $message);
}

private function detectCsvDelimiter(string $line): string
{
    $delimiters = [',', ';', "\t"];
    $bestDelimiter = ',';
    $bestCount = 0;

    foreach ($delimiters as $delimiter) {
        $count = count(str_getcsv($line, $delimiter));

        if ($count > $bestCount) {
            $bestCount = $count;
            $bestDelimiter = $delimiter;
        }
    }

    return $bestDelimiter;
}

private function normalizeCsvHeaders(array $headers): array
{
    return array_map(function ($header) {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

        return Str::of($header)->trim()->lower()->replace(' ', '_')->toString();
    }, $headers);
}

private function combineCsvRow(array $headers, array $row): array
{
    $row = array_pad($row, count($headers), null);

    return array_combine($headers, array_slice($row, 0, count($headers))) ?: [];
}

private function isEmptyCsvRow(array $row): bool
{
    return trim(implode('', $row)) === '';
}

private function normalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

private function normalizeMoney($value): float
{
    $value = trim((string) $value);
    $value = str_replace(['$', ' ', ','], '', $value);

    return max(0, (float) $value);
}

private function normalizeLegacySex($sex): string
{
    $sex = Str::of((string) $sex)->trim()->lower()->ascii()->toString();

    return match ($sex) {
        'm', 'macho', 'male' => 'male',
        'h', 'hembra', 'f', 'female' => 'female',
        default => 'unknown',
    };
}

private function buildHorseLegacyNotes(array $data, string $legacyHorseId, string $legacyCustomerId): string
{
    $parts = [
        'Importado desde legacy.',
        'Legacy CaballoID: ' . ($legacyHorseId !== '' ? $legacyHorseId : 'sin-id') . '.',
        'Legacy ClienteID: ' . ($legacyCustomerId !== '' ? $legacyCustomerId : 'sin-id') . '.',
    ];

    foreach ([
        'raza' => 'Raza',
        'clubid' => 'Legacy ClubID',
        'fotochip' => 'Foto chip',
    ] as $key => $label) {
        $value = trim((string) ($data[$key] ?? ''));

        if ($value !== '') {
            $parts[] = "{$label}: {$value}.";
        }
    }

    return trim(implode(' ', $parts));
}

private function resolveHorseAnimalTypeId(int $tenantId): int
{
    $horseType = AnimalType::query()
        ->where('tenant_id', $tenantId)
        ->where(function ($query) {
            $query->where('slug', 'like', 'caballo%')
                ->orWhere('name', 'like', 'Caballo%')
                ->orWhere('name', 'like', 'caballo%');
        })
        ->orderBy('id')
        ->first();

    if ($horseType) {
        return $horseType->id;
    }

    return AnimalType::create([
        'tenant_id' => $tenantId,
        'name' => 'Caballos',
        'slug' => 'caballos',
        'description' => 'Tipo creado automaticamente al importar caballos legacy.',
        'is_active' => true,
    ])->id;
}

private function resolveLegacyClub(int $tenantId, $legacyClubId): ?Club
{
    $legacyClubId = trim((string) $legacyClubId);

    if ($legacyClubId === '' || strtoupper($legacyClubId) === 'NULL' || $legacyClubId === '0') {
        return null;
    }

    return Club::query()->firstOrCreate(
        [
            'tenant_id' => $tenantId,
            'name' => 'Club legacy ' . $legacyClubId,
        ],
        [
            'description' => 'Importado desde legacy. Legacy ClubID: ' . $legacyClubId . '.',
            'is_active' => true,
        ]
    );
}

private function parseLegacyDate($date): ?Carbon
{
    if (blank($date)) {
        return null;
    }

    $rawDate = trim((string) $date);

    if (
        $rawDate === ''
        || str_starts_with($rawDate, '0000-00-00')
        || str_starts_with($rawDate, '0001-01-01')
        || preg_match('/^0{1,2}[\/\-]0{1,2}[\/\-]0{2,4}/', $rawDate)
    ) {
        return null;
    }

    try {
        $parsedDate = Carbon::parse($rawDate);

        if ((int) $parsedDate->format('Y') < 1900) {
            return null;
        }

        return $parsedDate;
    } catch (\Throwable $exception) {
        return null;
    }
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
public function stripeCheckout(Request $request)
{
    $tenant = auth()->user()->tenant()->with('plan')->firstOrFail();

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
            route('client.mi-configuracion.index') . '?stripe_success=1',
            route('client.mi-configuracion.index') . '?stripe_cancel=1',
            auth()->id()
        );
    } catch (\Throwable $exception) {
        report($exception);

        return back()
            ->with('activeTab', 'facturacion')
            ->with('error', 'No se pudo abrir Stripe Checkout: ' . $exception->getMessage());
    }

    return redirect($session->url);
}
public function guardarFacturacion(Request $request)
{
        

    $tenant = auth()->user()->tenant;

    $validated = $request->validate([
        'legal_name'         => 'required|string|max:255',
        'tax_id'             => 'required|string|max:13',
        'tax_system'         => 'required|string|max:3',
        'zip'                => 'required|string|size:5',
        'email'              => 'nullable|email|max:255',

        'facturapi_api_key'  => 'nullable|string|max:500',

        'csd_password'       => 'nullable|string|max:255',

        // 'csd_cer'            => 'nullable|file|mimes:cer|max:2048',
        // 'csd_key'            => 'nullable|file|mimes:key|max:2048',
        'csd_cer' => 'nullable|file|max:4096',
        'csd_key' => 'nullable|file|max:4096',
    ]);
if ($request->hasFile('csd_cer')) {

    $extension = strtolower(
        $request->file('csd_cer')->getClientOriginalExtension()
    );

    if ($extension !== 'cer') {
        return back()
            ->withErrors([
                'csd_cer' => 'El archivo debe tener extensión .cer'
            ])
            ->withInput();
    }
}

if ($request->hasFile('csd_key')) {

    $extension = strtolower(
        $request->file('csd_key')->getClientOriginalExtension()
    );

    if ($extension !== 'key') {
        return back()
            ->withErrors([
                'csd_key' => 'El archivo debe tener extensión .key'
            ])
            ->withInput();
    }
}
    $billingProfile = TenantBillingProfile::firstOrNew([
        'tenant_id' => $tenant->id,
    ]);

    $billingProfile->fill([
        'legal_name'        => strtoupper($validated['legal_name']),
        'tax_id'            => strtoupper($validated['tax_id']),
        'tax_system'        => $validated['tax_system'],
        'zip'               => $validated['zip'],
        'email'             => $validated['email'] ?? null,
        'facturapi_api_key' => $validated['facturapi_api_key']
                                ?? $billingProfile->facturapi_api_key,
        'csd_password'      => $validated['csd_password']
                                ?? $billingProfile->csd_password,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Subir certificado .CER
    |--------------------------------------------------------------------------
    */
    if ($request->hasFile('csd_cer')) {

        // Eliminar anterior
        if (
            $billingProfile->csd_cer_path &&
            Storage::disk('public')->exists($billingProfile->csd_cer_path)
        ) {
            Storage::disk('public')->delete($billingProfile->csd_cer_path);
        }

        $billingProfile->csd_cer_path = $request->file('csd_cer')
            ->store(
                "tenants/{$tenant->id}/facturacion",
                'public'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Subir certificado .KEY
    |--------------------------------------------------------------------------
    */
    if ($request->hasFile('csd_key')) {

        if (
            $billingProfile->csd_key_path &&
            Storage::disk('public')->exists($billingProfile->csd_key_path)
        ) {
            Storage::disk('public')->delete($billingProfile->csd_key_path);
        }

        $billingProfile->csd_key_path = $request->file('csd_key')
            ->store(
                "tenants/{$tenant->id}/facturacion",
                'public'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Estado configuración
    |--------------------------------------------------------------------------
    */
    $billingProfile->csd_uploaded =
        !empty($billingProfile->csd_cer_path) &&
        !empty($billingProfile->csd_key_path);

    $billingProfile->is_active =
        !empty($billingProfile->tax_id) &&
        !empty($billingProfile->tax_system) &&
        !empty($billingProfile->zip) &&
        !empty($billingProfile->facturapi_api_key);

    $billingProfile->save();

    return redirect()
        ->back()
        ->with(
            'success',
            'Configuración fiscal guardada correctamente.'
        );
}
}
