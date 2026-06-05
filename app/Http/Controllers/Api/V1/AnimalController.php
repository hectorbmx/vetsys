<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnimalController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'deceased', 'transferred'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $animals = Animal::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when(isset($data['since']), function (Builder $query) use ($data) {
                $query->where(function (Builder $query) use ($data) {
                    $query->where('updated_at', '>=', $data['since'])
                        ->orWhere('deleted_at', '>=', $data['since']);
                });
            })
            ->when(isset($data['customer_id']), fn (Builder $query) => $query->where('customer_id', $data['customer_id']))
            ->when(isset($data['status']), fn (Builder $query) => $query->where('status', $data['status']))
            ->when(isset($data['q']), function (Builder $query) use ($data) {
                $search = $data['q'];

                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%")
                        ->orWhere('microchip', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $animals->getCollection()->map(fn (Animal $animal) => $this->serializeAnimal($animal)),
            'meta' => [
                'current_page' => $animals->currentPage(),
                'last_page' => $animals->lastPage(),
                'per_page' => $animals->perPage(),
                'total' => $animals->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->validatedData($request, $tenantId, false, false);

        if (!empty($data['client_uuid'])) {
            $existing = Animal::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => $this->serializeAnimal($existing),
                    'idempotent' => true,
                ]);
            }
        }

        $animal = Animal::create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => $data['status'] ?? 'active',
            'synced_from_mobile' => true,
        ]);

        return response()->json([
            'data' => $this->serializeAnimal($animal),
            'idempotent' => false,
        ], 201);
    }

    public function show(Request $request, Animal $animal)
    {
        $this->authorizeTenant($request, $animal);

        return response()->json([
            'data' => $this->serializeAnimal($animal),
        ]);
    }

    public function update(Request $request, Animal $animal)
    {
        $this->authorizeTenant($request, $animal);

        $data = $this->validatedData($request, $request->user()->tenant_id, true);

        $animal->update([
            ...$data,
            'synced_from_mobile' => true,
        ]);

        return response()->json([
            'data' => $this->serializeAnimal($animal->fresh()),
        ]);
    }

    private function validatedData(Request $request, int $tenantId, bool $partial = false, bool $enforceUniqueClientUuid = true): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $clientUuidRules = ['nullable', 'uuid'];

        if ($enforceUniqueClientUuid) {
            $clientUuidRules[] = Rule::unique('animals', 'client_uuid')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                ->ignore($request->route('animal'));
        }

        return $request->validate([
            'client_uuid' => $clientUuidRules,
            'customer_id' => [
                $required,
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'club_id' => [
                'nullable',
                'integer',
                Rule::exists('clubs', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'animal_type_id' => [
                $required,
                'integer',
                Rule::exists('animal_types', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'name' => [$required, 'string', 'max:255'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'sex' => ['sometimes', Rule::in(['male', 'female', 'unknown'])],
            'birthdate' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'microchip' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'deceased', 'transferred'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function authorizeTenant(Request $request, Animal $animal): void
    {
        abort_if($animal->tenant_id !== $request->user()->tenant_id, 404);
    }

    private function serializeAnimal(Animal $animal): array
    {
        return [
            'id' => $animal->id,
            'client_uuid' => $animal->client_uuid,
            'customer_id' => $animal->customer_id,
            'club_id' => $animal->club_id,
            'animal_type_id' => $animal->animal_type_id,
            'name' => $animal->name,
            'photo_path' => $animal->photo_path,
            'sex' => $animal->sex,
            'birthdate' => $animal->birthdate?->toDateString(),
            'color' => $animal->color,
            'weight' => $animal->weight,
            'microchip' => $animal->microchip,
            'status' => $animal->status,
            'notes' => $animal->notes,
            'synced_from_mobile' => $animal->synced_from_mobile,
            'created_at' => $animal->created_at?->toISOString(),
            'updated_at' => $animal->updated_at?->toISOString(),
            'deleted_at' => $animal->deleted_at?->toISOString(),
        ];
    }
}
