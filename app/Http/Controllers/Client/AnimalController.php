<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Animal;
use App\Models\Customer;
use App\Models\AnimalType;
use App\Models\Club;
use App\Services\TenantOnboardingService;
use App\Services\CustomerPortalAccessService;
use App\Services\MicrochipImageOptimizer;
use App\Services\MicrochipLetterPdfService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Exception;

class AnimalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    $tenantId = auth()->user()->tenant_id;
    $allowedPerPage = [15, 30, 50, 100];
    $requestedPerPage = $request->integer('per_page', 15);
    $perPage = in_array($requestedPerPage, $allowedPerPage, true)
        ? $requestedPerPage
        : 15;

    $tenantAnimals = Animal::query()->where('tenant_id', $tenantId);
    $totalAnimals = (clone $tenantAnimals)->count();
    $inactiveAnimals = (clone $tenantAnimals)->where('status', 'inactive')->count();

    // 1. Cargamos relaciones reales del modelo: customer y animalType
    $animals = Animal::with(['customer', 'animalType', 'club'])
        ->where('tenant_id', $tenantId)
        ->when($request->filled('q'), function ($query) use ($request) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('color', 'like', "%{$search}%") // Usamos color o microchip de tu fillable
                  ->orWhere('microchip', 'like', "%{$search}%")
                  // Búsqueda por el nombre del tipo de animal (Especie)
                  ->orWhereHas('animalType', function ($typeQuery) use ($search) {
                      $typeQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('club', function ($clubQuery) use ($search) {
                      $clubQuery->where('name', 'like', "%{$search}%");
                  })
                  // Búsqueda por el nombre del dueño
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        })
        ->latest()
        ->paginate($perPage)
        ->withQueryString();

    // 2. Clientes activos para el modal
    $customers = Customer::where('tenant_id', $tenantId)
        ->where('status', 'active')
        ->get();

    // 3. NUEVO: Cargamos los tipos de animales configurados y activos por el veterinario
    $animalTypes = \App\Models\AnimalType::where('tenant_id', $tenantId)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    $clubs = Club::where('tenant_id', $tenantId)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    return view('client.animals.index', compact(
        'animals',
        'customers',
        'animalTypes',
        'clubs',
        'totalAnimals',
        'inactiveAnimals',
        'perPage'
    ));
}

    public function toggleStatus(Animal $animal)
    {
        if ($animal->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $animal->update([
            'status' => $animal->status === 'active' ? 'inactive' : 'active'
        ]);

        if ($animal->status === 'active') {
            app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
        }

        return back()->with('success', 'El estatus de la mascota ha sido actualizado.');
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
    // dd($request->all());
    $tenantId = auth()->user()->tenant_id;

    // 1. Validamos usando los campos EXACTOS de tu modelo Animal
    $data = $request->validate([
        'customer_id'    => [
            'required',
            Rule::exists('customers', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')),
        ],
        'club_id' => [
            'nullable',
            Rule::exists('clubs', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
        ],
        'animal_type_id' => [
            'required',
            Rule::exists('animal_types', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)),
        ],
        'name'           => ['required', 'string', 'max:255'],
        'sex'            => ['required', 'in:male,female,unknown'], // Campos de tu fillable
        'birthdate'      => ['nullable', 'date'],          // Sin guion bajo_
        'color'          => ['nullable', 'string', 'max:100'],
        'weight'         => ['nullable', 'numeric', 'between:0,999.99'],
        'microchip'      => ['nullable', 'string', 'regex:/^\d{15}$/'],
        'notes'          => ['nullable', 'string'],
        'allergies'      => ['nullable', 'string'],
    ], [
        'microchip.regex' => 'El microchip debe contener exactamente 15 digitos numericos.',
    ]);

    // 2. Inyectamos los datos obligatorios del sistema
    $data['tenant_id'] = $tenantId;
    $data['status']    = 'active'; 

    try {
    $animal = Animal::create($data);

    app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);

    // Si viene desde el perfil de un cliente, regresamos a él
    if ($request->filled('redirect_to')) {
        return redirect()
            ->to($request->redirect_to)
            ->with('success', 'Paciente registrado con éxito!');
    }

    return redirect()
        ->route('client.animals.index')
        ->with('success', '¡Excelente! El paciente ha sido registrado con éxito.');
        
} catch (\Exception $e) {
    return redirect()
        ->back()
        ->withInput()
        ->with('error', 'Hubo un problema al guardar el paciente: ' . $e->getMessage());
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
    public function edit(Animal $animal)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($animal->tenant_id === $tenantId, 404);

        $animal->load([
            'customer',
            'animalType',
            'club',
            'shares.sharedWithTenant',
            'vaccinationLetters' => fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at')
                ->orderBy('id'),
            'videos' => fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->latest('video_date')
                ->latest('id'),
            'radiologyStudies' => fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->with(['images' => fn ($imageQuery) => $imageQuery->latest('id')])
                ->latest('study_date')
                ->latest('id'),
            'reports' => fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->with(['author', 'images'])
                ->latest('report_date')
                ->latest('id'),
        ]);

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $animalTypes = AnimalType::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $clubs = Club::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $serviceHistory = $animal->noteDetails()
            ->where('tenant_id', $tenantId)
            ->with(['note.customer', 'catalogItem'])
            ->latest()
            ->get();

        $portalUserIds = $animal->customer?->portalAccesses()
            ->where('status', 'active')
            ->pluck('user_id') ?? collect();
        $hasActivePortalAccess = $portalUserIds->isNotEmpty();
        $isVisibleInPortal = $hasActivePortalAccess && $animal->finalUserPatientAssignments()
            ->whereIn('user_id', $portalUserIds)
            ->whereNull('revoked_at')
            ->exists();

        return view('client.animals.edit', compact(
            'animal',
            'customers',
            'animalTypes',
            'clubs',
            'serviceHistory',
            'hasActivePortalAccess',
            'isVisibleInPortal'
        ));
    }

    public function togglePortalVisibility(Animal $animal, CustomerPortalAccessService $portalAccessService)
    {
        abort_unless($animal->tenant_id === auth()->user()->tenant_id, 404);

        try {
            $isVisible = $portalAccessService->toggleAnimalVisibility($animal, auth()->user());

            return redirect()
                ->route('client.animals.edit', $animal)
                ->with('success', $isVisible
                    ? 'El paciente ahora es visible en la app del cliente.'
                    : 'El paciente se oculto de la app del cliente.')
                ->with('animalTab', 'datos');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('client.animals.edit', $animal)
                ->with('error', $exception->getMessage())
                ->with('animalTab', 'datos');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        Request $request,
        Animal $animal,
        MicrochipImageOptimizer $imageOptimizer,
        MicrochipLetterPdfService $microchipPdfService
    )
    {
        $tenantId = auth()->user()->tenant_id;

        abort_unless($animal->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'club_id' => [
                'nullable',
                Rule::exists('clubs', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'animal_type_id' => [
                'required',
                Rule::exists('animal_types', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:male,female,unknown'],
            'birthdate' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'numeric', 'between:0,999.99'],
            'microchip' => ['nullable', 'string', 'regex:/^\d{15}$/'],
            'microchip_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'status' => ['required', 'in:active,inactive,deceased,transferred'],
            'notes' => ['nullable', 'string'],
            'allergies' => ['nullable', 'string'],
        ], [
            'microchip.regex' => 'El microchip debe contener exactamente 15 digitos numericos.',
        ]);

        $newImagePath = null;

        try {
            $oldImagePath = null;

            if ($request->hasFile('microchip_image')) {
                $path = "tenants/{$tenantId}/animals/{$animal->id}/microchip/".Str::uuid().'.webp';
                $contents = $imageOptimizer->optimize($request->file('microchip_image'));
                Storage::disk('r2')->put($path, $contents, ['mimetype' => 'image/webp']);

                $newImagePath = $path;
                $oldImagePath = $animal->microchip_image_path;
                $data['microchip_image_path'] = $path;
                $data['microchip_print_token'] = $animal->microchip_print_token ?: (string) Str::uuid();
                $data['microchip_issued_by'] = auth()->id();
            } elseif ($animal->microchip_image_path && ! $animal->microchip_issued_by) {
                $data['microchip_issued_by'] = auth()->id();
            }

            unset($data['microchip_image']);

            $animal->update($data);

            if ($oldImagePath) {
                try {
                    Storage::disk('r2')->delete($oldImagePath);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            $microchipDocumentFields = [
                'name', 'customer_id', 'animal_type_id', 'birthdate', 'color', 'sex',
                'microchip', 'microchip_image_path', 'microchip_issued_by',
            ];
            if ($animal->microchip_image_path
                && (! $animal->microchip_pdf_path || $animal->wasChanged($microchipDocumentFields))) {
                $microchipPdfService->finalize($animal);
            }

            if ($animal->status === 'active') {
                app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
            }

            return redirect()
                ->route('client.animals.edit', $animal)
                ->with('success', 'Paciente actualizado correctamente.');
        } catch (Exception $e) {
            if ($newImagePath && $animal->microchip_image_path !== $newImagePath) {
                try {
                    Storage::disk('r2')->delete($newImagePath);
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Hubo un problema al actualizar la mascota: ' . $e->getMessage());
        }
    }

    public function destroyMicrochipImage(Animal $animal)
    {
        abort_unless($animal->tenant_id === auth()->user()->tenant_id, 404);

        if ($animal->microchip_image_path) {
            Storage::disk('r2')->delete($animal->microchip_image_path);
            if ($animal->microchip_pdf_path) {
                Storage::disk($animal->microchip_pdf_disk ?: 'r2')->delete($animal->microchip_pdf_path);
            }
            $animal->update([
                'microchip_image_path' => null,
                'microchip_print_token' => null,
                'microchip_issued_by' => null,
                'microchip_pdf_disk' => null,
                'microchip_pdf_path' => null,
                'microchip_finalized_at' => null,
            ]);
        }

        return redirect()
            ->route('client.animals.edit', $animal)
            ->with('success', 'La foto del microchip fue eliminada.')
            ->with('animalTab', 'datos');
    }

    public function publicMicrochipLetter(string $token, MicrochipLetterPdfService $pdfService)
    {
        $animal = Animal::query()
            ->where('microchip_print_token', $token)
            ->whereNotNull('microchip_image_path')
            ->firstOrFail();

        return redirect()->away($pdfService->temporaryUrl($animal));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
