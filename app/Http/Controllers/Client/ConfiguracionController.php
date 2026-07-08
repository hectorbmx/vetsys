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
use App\Models\CustomerPortalAccess;
use App\Models\Plan;
use App\Models\PriceHistory;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\TenantDocumentSetting;
use App\Models\TenantDocumentTemplate;
use App\Models\User;
use App\Models\VeterinarianProfile;
use App\Services\VeterinarianSignatureOptimizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
use App\Rules\GloballyUniqueEmail;
use App\Services\TenantOnboardingService;
use App\Services\LetterheadImageOptimizer;
use App\Services\TenantDocumentTemplateService;
use App\Services\AppointmentConfigurationService;
use App\Support\TenantHomeRoutes;
use App\Support\TenantMenuModules;
use App\Support\TenantThemePalettes;

class ConfiguracionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 public function index(AppointmentConfigurationService $appointmentConfigurationService)
{
    // TEMPORAL: Pon esto al inicio para validar si Laravel ya entra aquí
    // dd("¡ESTOY AQUÍ!");

    $user = auth()->user();
    $tenantId = $user->tenant_id;
    $tenant = $user->tenant()->with('plan')->first();
    $billingProfile = $tenant?->billingProfile;
    $documentSettings = $tenant?->documentSetting;
    $documentTemplates = app(TenantDocumentTemplateService::class)->forTenant($tenantId);
    $animalTypes = AnimalType::where('tenant_id', $tenantId)
        ->latest()
        ->get();

    $teamUsers = $this->teamUsersQuery($tenantId)
        ->with(['roles', 'veterinarianProfile'])
        ->latest()
        ->get();

    $portalAccesses = CustomerPortalAccess::query()
        ->where('tenant_id', $tenantId)
        ->whereHas('customer')
        ->with([
            'customer' => fn ($query) => $query->withCount('animals'),
            'user',
        ])
        ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
        ->latest('activated_at')
        ->get();
    $activePortalClients = $portalAccesses->where('status', 'active')->count();
    $professionalProfilesCount = $teamUsers->filter(fn ($teamUser) => $teamUser->veterinarianProfile)->count();
    $configuredSignaturesCount = $teamUsers->filter(fn ($teamUser) => filled($teamUser->veterinarianProfile?->signature_path))->count();

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
    $canManageTeam = $this->isTenantAdmin($user);
    $canManageAppearance = $this->canManageTenantAppearance($user, $tenant);
    $canManageDocuments = $this->canManageTenantAppearance($user, $tenant);
    $themePalettes = TenantThemePalettes::all();
    $activeThemePalette = TenantThemePalettes::normalize($tenant?->theme_palette);
    $homeRouteOptions = TenantHomeRoutes::all();
    $activeHomeRoute = TenantHomeRoutes::normalize($tenant?->default_home_route);
    $menuModuleOptions = TenantMenuModules::all();
    $visibleMenuModules = TenantMenuModules::normalize($tenant?->visible_menu_modules);
    $appointmentConfiguration = $appointmentConfigurationService->viewData($tenant, $user);

    $this->ensureTenantRolesExist();

    return view('client.mi-configuracion.index', array_merge(compact(
        'animalTypes',
        'tenant',
        'billingProfile',
        'documentSettings',
        'documentTemplates',
        'teamUsers',
        'portalAccesses',
        'activePortalClients',
        'professionalProfilesCount',
        'configuredSignaturesCount',
        'maxUsers',
        'usersUsed',
        'canInviteUsers',
        'roleOptions',
        'roleDescriptions',
        'canManageTeam',
        'canManageAppearance',
        'canManageDocuments',
        'activePlans',
        'subscriptionPayments',
        'pendingPlanRequest',
        'pendingPlanPayment',
        'themePalettes',
        'activeThemePalette',
        'homeRouteOptions',
        'activeHomeRoute',
        'menuModuleOptions',
        'visibleMenuModules'
    ), $appointmentConfiguration));
}

public function updateHomeRoute(Request $request)
{
    $user = $request->user();
    $tenant = $user->tenant;

    abort_unless($tenant && $this->isTenantAdmin($user), 403);

    $data = $request->validate([
        'default_home_route' => ['required', Rule::in(TenantHomeRoutes::keys())],
    ]);

    $tenant->update([
        'default_home_route' => $data['default_home_route'],
    ]);

    return redirect()
        ->route('client.mi-configuracion.index', ['tab' => 'preferencias'])
        ->with('success', 'Pantalla de inicio actualizada correctamente.');
}

public function updateMenuModules(Request $request)
{
    $user = $request->user();
    $tenant = $user->tenant;

    abort_unless($tenant && $this->isTenantAdmin($user), 403);

    $data = $request->validate([
        'visible_menu_modules' => ['nullable', 'array'],
        'visible_menu_modules.*' => ['string', Rule::in(TenantMenuModules::keys())],
    ]);

    $tenant->update([
        'visible_menu_modules' => TenantMenuModules::normalize($data['visible_menu_modules'] ?? []),
    ]);

    return redirect()
        ->route('client.mi-configuracion.index', ['tab' => 'preferencias'])
        ->with('success', 'Modulos visibles actualizados correctamente.');
}

public function updateThemePalette(Request $request)
{
    $user = $request->user();
    $tenant = $user->tenant;

    if (!$this->canManageTenantAppearance($user, $tenant)) {
        abort(403);
    }

    $data = $request->validate([
        'theme_palette' => ['required', Rule::in(TenantThemePalettes::keys())],
        'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        'remove_logo' => ['nullable', 'boolean'],
    ]);

    $updates = [
        'theme_palette' => $data['theme_palette'],
    ];

    if ($request->boolean('remove_logo') && $tenant?->logo) {
        Storage::disk('r2')->delete($tenant->logo);
        $updates['logo'] = null;
    }

    if ($request->hasFile('logo')) {
        if ($tenant?->logo) {
            Storage::disk('r2')->delete($tenant->logo);
        }

        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $path = 'tenants/'.$tenant->id.'/branding/logo-'.now()->format('YmdHis').'-'.Str::random(10).'.'.$extension;

        Storage::disk('r2')->put($path, fopen($file->getRealPath(), 'rb'), [
            'ContentType' => $file->getMimeType() ?: 'image/png',
        ]);

        $updates['logo'] = $path;
    }

    $tenant->update($updates);

    return redirect()
        ->route('client.mi-configuracion.index', ['tab' => 'apariencia'])
        ->with('success', 'Apariencia actualizada para todo el equipo.');
}

private function canManageTenantAppearance(User $user, ?Tenant $tenant): bool
{
    if (!$tenant) {
        return false;
    }

    return $this->isTenantAdmin($user)
        || (int) $tenant->created_by === (int) $user->id
        || strcasecmp((string) $tenant->email, (string) $user->email) === 0;
}

private function isTenantAdmin(User $user): bool
{
    return $user->hasAnyRole(['admin', 'client-admin']);
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

            app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);

            // CORRECCIÓN AQUÍ: Apuntar al nuevo nombre de la ruta
            return redirect()
                ->route('client.mi-configuracion.index')
                ->with('activeTab', 'animales')
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

    public function toggleStatus(AnimalType $animalType)
    {
        if ($animalType->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $animalType->is_active = !$animalType->is_active;
        $animalType->save();

        if ($animalType->is_active) {
            app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
        }

        return back()
            ->with('activeTab', 'animales')
            ->with('success', 'Estado de la especie actualizado.');
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
    abort_unless($this->isTenantAdmin(auth()->user()), 403);

    $tenant = auth()->user()->tenant()->with('plan')->firstOrFail();
    $usersUsed = $this->teamUsersQuery($tenant->id)->count();
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
            new GloballyUniqueEmail,
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

public function updateVeterinarianProfile(
    Request $request,
    User $teamUser,
    VeterinarianSignatureOptimizer $signatureOptimizer
) {
    $actor = $request->user();
    abort_unless($this->isTenantAdmin($actor), 403);
    abort_unless($teamUser->tenant_id === $actor->tenant_id, 404);
    abort_unless($this->teamUsersQuery($actor->tenant_id)->whereKey($teamUser->id)->exists(), 404);

    $profile = VeterinarianProfile::firstOrNew([
        'tenant_id' => $actor->tenant_id,
        'user_id' => $teamUser->id,
    ]);

    $data = $request->validate([
        'professional_name' => ['required', 'string', 'max:255'],
        'professional_title' => ['required', 'string', 'max:100'],
        'license_number' => [
            'required',
            'string',
            'max:100',
            Rule::unique('veterinarian_profiles', 'license_number')
                ->where(fn ($query) => $query->where('tenant_id', $actor->tenant_id))
                ->ignore($profile->id),
        ],
        'specialty' => ['nullable', 'string', 'max:255'],
        'professional_phone' => ['nullable', 'string', 'max:50'],
        'professional_email' => ['nullable', 'email', 'max:255'],
        'professional_address' => ['nullable', 'string', 'max:1000'],
        'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    $newSignaturePath = null;
    $profileSaved = false;
    $oldSignaturePath = $profile->signature_path;
    $oldSignatureDisk = $profile->signature_disk;

    try {
        if ($request->hasFile('signature')) {
            $newSignaturePath = "tenants/{$actor->tenant_id}/users/{$teamUser->id}/professional/signature-".
                Str::uuid().'.webp';
            $contents = $signatureOptimizer->optimize($request->file('signature'));
            Storage::disk('r2')->put($newSignaturePath, $contents, ['mimetype' => 'image/webp']);

            $data['signature_disk'] = 'r2';
            $data['signature_path'] = $newSignaturePath;
        }

        unset($data['signature']);
        $data['is_active'] = $request->boolean('is_active');
        $profile->fill($data)->save();
        $profileSaved = true;

        if ($newSignaturePath && $oldSignaturePath) {
            try {
                Storage::disk($oldSignatureDisk ?: 'r2')->delete($oldSignaturePath);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    } catch (\Throwable $exception) {
        if ($newSignaturePath && !$profileSaved) {
            try {
                Storage::disk('r2')->delete($newSignaturePath);
            } catch (\Throwable $cleanupException) {
                report($cleanupException);
            }
        }

        report($exception);

        return back()
            ->withInput()
            ->with('activeTab', 'usuarios')
            ->with('professionalProfileUserId', $teamUser->id)
            ->with('error', 'No se pudo guardar el perfil profesional. Intenta nuevamente.');
    }

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'usuarios')
        ->with('success', 'Perfil profesional actualizado correctamente.');
}

public function updateDocumentSettings(
    Request $request,
    LetterheadImageOptimizer $letterheadOptimizer
) {
    $user = $request->user();
    $tenant = $user->tenant;
    abort_unless($tenant && $this->canManageTenantAppearance($user, $tenant), 403);

    $request->validate([
        'letterhead' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
    ]);

    $settings = TenantDocumentSetting::firstOrNew(['tenant_id' => $tenant->id]);
    $oldPath = $settings->letterhead_path;
    $oldDisk = $settings->letterhead_disk;
    $newPath = "tenants/{$tenant->id}/documents/letterhead-".Str::uuid().'.webp';
    $stored = false;
    $settingsSaved = false;

    try {
        $contents = $letterheadOptimizer->optimize($request->file('letterhead'));
        Storage::disk('r2')->put($newPath, $contents, ['mimetype' => 'image/webp']);
        $stored = true;

        $settings->fill([
            'letterhead_disk' => 'r2',
            'letterhead_path' => $newPath,
            'letterhead_original_name' => $request->file('letterhead')->getClientOriginalName(),
            'letterhead_size' => strlen($contents),
        ])->save();
        $settingsSaved = true;

        if ($oldPath) {
            try {
                Storage::disk($oldDisk ?: 'r2')->delete($oldPath);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    } catch (\Throwable $exception) {
        if ($stored && !$settingsSaved) {
            try {
                Storage::disk('r2')->delete($newPath);
            } catch (\Throwable $cleanupException) {
                report($cleanupException);
            }
        }

        report($exception);

        return back()
            ->withInput()
            ->with('activeTab', 'documentos')
            ->with('error', 'No se pudo guardar el membrete. Intenta nuevamente.');
    }

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'documentos')
        ->with('success', 'Membrete actualizado correctamente.');
}

public function letterhead(TenantDocumentSetting $tenantDocumentSetting)
{
    abort_unless($tenantDocumentSetting->tenant_id === auth()->user()->tenant_id, 404);
    abort_unless($tenantDocumentSetting->letterhead_path, 404);
    abort_unless(Storage::disk($tenantDocumentSetting->letterhead_disk)->exists($tenantDocumentSetting->letterhead_path), 404);

    return redirect()->away(
        Storage::disk($tenantDocumentSetting->letterhead_disk)
            ->temporaryUrl($tenantDocumentSetting->letterhead_path, now()->addMinutes(30))
    );
}

public function destroyLetterhead(TenantDocumentSetting $tenantDocumentSetting)
{
    $user = auth()->user();
    abort_unless($tenantDocumentSetting->tenant_id === $user->tenant_id, 404);
    abort_unless($this->canManageTenantAppearance($user, $user->tenant), 403);

    if ($tenantDocumentSetting->letterhead_path) {
        $disk = $tenantDocumentSetting->letterhead_disk;
        $path = $tenantDocumentSetting->letterhead_path;
        $tenantDocumentSetting->update([
            'letterhead_disk' => null,
            'letterhead_path' => null,
            'letterhead_original_name' => null,
            'letterhead_size' => null,
        ]);

        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'documentos')
        ->with('success', 'Membrete eliminado correctamente.');
}

public function updateDocumentTemplate(
    Request $request,
    string $type,
    TenantDocumentTemplateService $templateService
) {
    $user = $request->user();
    $tenant = $user->tenant;
    abort_unless($tenant && $this->canManageTenantAppearance($user, $tenant), 403);
    abort_unless(in_array($type, $templateService->types(), true), 404);

    $data = $request->validate([
        'body_html' => ['nullable', 'string', 'max:100000'],
        'header_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        'closing_text' => ['nullable', 'string', 'max:2000'],
        'image_section_title' => ['nullable', 'string', 'max:255'],
        'document_template_type' => ['required', Rule::in([$type])],
    ]);

    $bodyHtml = $templateService->sanitizeAndValidate($type, $data['body_html'] ?? '');
    $closingText = $templateService->validatePlainText($type, $data['closing_text'] ?? '', 'closing_text');
    $imageSectionTitle = $templateService->validatePlainText($type, $data['image_section_title'] ?? '', 'image_section_title');
    if ($type !== TenantDocumentTemplateService::CLINICAL_REPORT && trim(strip_tags($bodyHtml)) === '') {
        throw ValidationException::withMessages([
            'body_html' => 'Escribe el texto principal de la carta.',
        ]);
    }

    TenantDocumentTemplate::updateOrCreate(
        [
            'tenant_id' => $tenant->id,
            'document_type' => $type,
        ],
        [
            'body_html' => $bodyHtml,
            'header_color' => strtoupper($data['header_color']),
            'closing_text' => $closingText ?: null,
            'image_section_title' => $imageSectionTitle ?: null,
            'updated_by' => $user->id,
        ]
    );

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'documentos')
        ->with('documentTemplateOpen', $type)
        ->with('success', 'Plantilla actualizada correctamente.');
}

public function restoreDocumentTemplate(
    Request $request,
    string $type,
    TenantDocumentTemplateService $templateService
) {
    $user = $request->user();
    $tenant = $user->tenant;
    abort_unless($tenant && $this->canManageTenantAppearance($user, $tenant), 403);
    abort_unless(in_array($type, $templateService->types(), true), 404);

    TenantDocumentTemplate::query()
        ->where('tenant_id', $tenant->id)
        ->where('document_type', $type)
        ->delete();

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'documentos')
        ->with('success', 'Plantilla restaurada a su texto predeterminado.');
}

public function veterinarianSignature(VeterinarianProfile $veterinarianProfile)
{
    abort_unless($veterinarianProfile->tenant_id === auth()->user()->tenant_id, 404);
    abort_unless($veterinarianProfile->signature_path, 404);
    abort_unless(Storage::disk($veterinarianProfile->signature_disk)->exists($veterinarianProfile->signature_path), 404);

    return redirect()->away(
        Storage::disk($veterinarianProfile->signature_disk)
            ->temporaryUrl($veterinarianProfile->signature_path, now()->addMinutes(30))
    );
}

public function destroyVeterinarianSignature(VeterinarianProfile $veterinarianProfile)
{
    abort_unless($this->isTenantAdmin(auth()->user()), 403);
    abort_unless($veterinarianProfile->tenant_id === auth()->user()->tenant_id, 404);

    if ($veterinarianProfile->signature_path) {
        Storage::disk($veterinarianProfile->signature_disk)->delete($veterinarianProfile->signature_path);
        $veterinarianProfile->update([
            'signature_disk' => null,
            'signature_path' => null,
        ]);
    }

    return redirect()
        ->route('client.mi-configuracion.index')
        ->with('activeTab', 'usuarios')
        ->with('professionalProfileUserId', $veterinarianProfile->user_id)
        ->with('success', 'Firma eliminada correctamente.');
}

private function tenantRoleOptions(): array
{
    return [
        'client-admin' => 'Administrador',
        'asistente' => 'Asistente',
        'cajero' => 'Cajero',
    ];
}

private function teamUsersQuery(int $tenantId): Builder
{
    return User::query()
        ->where('tenant_id', $tenantId)
        ->where(function ($query) {
            $query->whereDoesntHave('roles')
                ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', '!=', 'customer'));
        });
}

private function tenantRoleDescriptions(): array
{
    return [
        'client-admin' => 'Administra usuarios, configuracion y operacion del tenant.',
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
            $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
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

            if ($validEmail && $this->emailExistsGlobally($validEmail)) {
                $errors[] = "Fila {$rowNumber}: correo ya registrado en el sistema.";
                $skipped++;
                continue;
            }

            $createdAt = $this->parseLegacyDate($data['created_at'] ?? null);

            $customer = new Customer([
                'tenant_id' => $tenantId,
                'name' => $name,
                'last_name' => $lastName !== '' ? $lastName : null,
                'email' => $validEmail,
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

    if ($created > 0) {
        app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
    }

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

    if ($created > 0) {
        app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
    }

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

    if ($created > 0) {
        app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
    }

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

private function emailExistsGlobally(string $email): bool
{
    $email = strtolower(trim($email));

    foreach (['users', 'customers'] as $table) {
        if (DB::table($table)->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return true;
        }
    }

    return false;
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
            route('client.profile.index') . '?stripe_success=1',
            route('client.profile.index') . '?stripe_cancel=1',
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
