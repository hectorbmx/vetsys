<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $customers = Customer::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when(isset($data['since']), function (Builder $query) use ($data) {
                $query->where(function (Builder $query) use ($data) {
                    $query->where('updated_at', '>=', $data['since'])
                        ->orWhere('deleted_at', '>=', $data['since']);
                });
            })
            ->when(isset($data['q']), function (Builder $query) use ($data) {
                $search = $data['q'];

                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(isset($data['status']), fn (Builder $query) => $query->where('status', $data['status']))
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $customers->getCollection()->map(fn (Customer $customer) => $this->serializeCustomer($customer)),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->validatedData($request, $tenantId, false, false);

        if (!empty($data['client_uuid'])) {
            $existing = Customer::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => $this->serializeCustomer($existing),
                    'idempotent' => true,
                ]);
            }
        }

        $customer = Customer::create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => $data['status'] ?? 'active',
            'synced_from_mobile' => true,
        ]);

        return response()->json([
            'data' => $this->serializeCustomer($customer),
            'idempotent' => false,
        ], 201);
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorizeTenant($request, $customer);

        return response()->json([
            'data' => $this->serializeCustomer($customer, true),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeTenant($request, $customer);

        $data = $this->validatedData($request, $request->user()->tenant_id, true);

        $customer->update([
            ...$data,
            'synced_from_mobile' => true,
        ]);

        return response()->json([
            'data' => $this->serializeCustomer($customer->fresh()),
        ]);
    }

    private function validatedData(Request $request, int $tenantId, bool $partial = false, bool $enforceUniqueClientUuid = true): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $clientUuidRules = ['nullable', 'uuid'];

        if ($enforceUniqueClientUuid) {
            $clientUuidRules[] = Rule::unique('customers', 'client_uuid')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                ->ignore($request->route('customer'));
        }

        return $request->validate([
            'client_uuid' => $clientUuidRules,
            'name' => [$required, 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeTenant(Request $request, Customer $customer): void
    {
        abort_if($customer->tenant_id !== $request->user()->tenant_id, 404);
    }

    private function serializeCustomer(Customer $customer, bool $withSummary = false): array
    {
        $data = [
            'id' => $customer->id,
            'client_uuid' => $customer->client_uuid,
            'name' => $customer->name,
            'last_name' => $customer->last_name,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'secondary_phone' => $customer->secondary_phone,
            'address' => $customer->address,
            'notes' => $customer->notes,
            'status' => $customer->status,
            'synced_from_mobile' => $customer->synced_from_mobile,
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
            'deleted_at' => $customer->deleted_at?->toISOString(),
        ];

        if ($withSummary) {
            $activeNotes = $customer->saleNotes()
                ->where('status', '!=', 'CANCELADA');

            $data['notes_count'] = (clone $activeNotes)->count();
            $data['pending_balance'] = (float) (clone $activeNotes)
                ->where('status', 'PENDIENTE')
                ->get()
                ->sum(fn ($note) => $note->balance);
            $data['credit_balance'] = $customer->credit_balance;
        }

        return $data;
    }
}
