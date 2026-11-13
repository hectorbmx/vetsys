<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Note;
use App\Models\VeterinarianProfile;
use App\Services\DocumentPresentationService;
use App\Services\InventoryService;
use App\Services\PortalNotificationService;
use App\Services\TenantDocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    public function pdf(
        Request $request,
        Budget $budget,
        TenantDocumentTemplateService $templateService,
        DocumentPresentationService $documentPresentationService
    ) {
        abort_unless($budget->tenant_id === $request->user()->tenant_id, 404);

        $budget->load([
            'tenant',
            'customer',
            'animals' => fn ($query) => $query->orderBy('position')->orderBy('id'),
            'animals.animal',
            'animals.items' => fn ($query) => $query->orderBy('position')->orderBy('id'),
            'animals.items.catalogItem',
        ]);

        $statusLabels = [
            Budget::STATUS_DRAFT => 'Borrador',
            Budget::STATUS_SENT => 'Enviado',
            Budget::STATUS_ACCEPTED => 'Aceptado',
            Budget::STATUS_REJECTED => 'Rechazado',
            Budget::STATUS_EXPIRED => 'Expirado',
            Budget::STATUS_CONVERTED => 'Convertido',
        ];

        $template = $templateService->forTenantAndType($budget->tenant_id, TenantDocumentTemplateService::BUDGET);
        $templateValues = [
            'customer_name' => $budget->customer?->full_name ?? '',
            'budget_folio' => $budget->folio,
            'budget_date' => $budget->budget_date?->format('d/m/Y') ?? '',
            'valid_until' => $budget->valid_until?->format('d/m/Y') ?? 'Sin vigencia',
            'budget_status' => $statusLabels[$budget->status] ?? $budget->status,
            'budget_total' => '$'.number_format((float) $budget->total, 2),
            'services_count' => (string) $budget->items->count(),
            'animals_count' => (string) $budget->animals->count(),
            'clinic_name' => $budget->tenant?->business_name ?: $budget->tenant?->name ?: '',
        ];

        $documentTemplate = [
            'body_html' => $templateService->render(TenantDocumentTemplateService::BUDGET, $template['body_html'], $templateValues),
            'header_color' => $template['header_color'],
            'closing_text' => $templateService->renderPlainText(TenantDocumentTemplateService::BUDGET, $template['closing_text'], $templateValues),
            'image_section_title' => $templateService->renderPlainText(TenantDocumentTemplateService::BUDGET, $template['image_section_title'], $templateValues),
        ];
        $signingProfile = $this->budgetSigningProfile($request, $budget);
        $signatureDataUri = $signingProfile?->signature_path
            ? $documentPresentationService->dataUri($signingProfile->signature_disk ?: 'r2', $signingProfile->signature_path)
            : null;

        $pdf = Pdf::loadView('client.budgets.pdf', [
            'budget' => $budget,
            'tenant' => $budget->tenant,
            'customer' => $budget->customer,
            'statusLabels' => $statusLabels,
            'logoUrl' => $budget->tenant?->logoUrl(),
            'documentTemplate' => $documentTemplate,
            'signingProfile' => $signingProfile,
            'signatureDataUri' => $signatureDataUri,
        ])
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'presupuesto-' . str($budget->folio)->slug() . '.pdf';

        return $pdf->download($filename);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id))],
            'budget_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:budget_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'animals' => ['required', 'array', 'min:1'],
            'animals.*.animal_id' => ['required', 'integer'],
            'animals.*.items' => ['required', 'array', 'min:1'],
            'animals.*.items.*.catalog_item_id' => ['required', 'integer'],
            'animals.*.items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'animals.*.items.*.price_at_budget' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $animalIds = collect($data['animals'])->pluck('animal_id')->map(fn ($id) => (int) $id)->unique()->values();
        $validAnimalIds = $tenant->customers()->whereKey($data['customer_id'])->firstOrFail()
            ->animals()->whereIn('id', $animalIds)->pluck('id')->map(fn ($id) => (int) $id);

        if ($validAnimalIds->count() !== $animalIds->count()) {
            return response()->json(['message' => 'Uno o mas caballos no pertenecen al cliente seleccionado.'], 422);
        }

        $catalogItemIds = collect($data['animals'])->flatMap(fn ($animal) => collect($animal['items'])->pluck('catalog_item_id'))
            ->map(fn ($id) => (int) $id)->unique()->values();
        $catalogItems = $tenant->catalogItems()->whereIn('id', $catalogItemIds)->where('is_active', true)->get()->keyBy('id');

        if ($catalogItems->count() !== $catalogItemIds->count()) {
            return response()->json(['message' => 'Uno o mas conceptos no pertenecen al catalogo activo del tenant.'], 422);
        }

        $budget = DB::transaction(function () use ($tenant, $data, $catalogItems) {
            $sequence = (Budget::withTrashed()->where('tenant_id', $tenant->id)->lockForUpdate()->max('id') ?? 0) + 1;
            $folio = 'PRES-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

            $budget = $tenant->budgets()->create([
                'customer_id' => $data['customer_id'],
                'folio' => $folio,
                'status' => Budget::STATUS_DRAFT,
                'budget_date' => $data['budget_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 0,
            ]);
            $total = 0;

            foreach (array_values($data['animals']) as $animalPosition => $animalData) {
                $budgetAnimal = $budget->animals()->create([
                    'tenant_id' => $tenant->id,
                    'animal_id' => $animalData['animal_id'],
                    'position' => $animalPosition,
                    'subtotal' => 0,
                ]);
                $animalTotal = 0;

                foreach (array_values($animalData['items']) as $itemPosition => $itemData) {
                    $catalogItem = $catalogItems->get((int) $itemData['catalog_item_id']);
                    $quantity = round((float) $itemData['quantity'], 2);
                    $price = round((float) $itemData['price_at_budget'], 2);
                    $subtotal = round($quantity * $price, 2);
                    $animalTotal += $subtotal;
                    $budgetAnimal->items()->create([
                        'tenant_id' => $tenant->id,
                        'budget_id' => $budget->id,
                        'animal_id' => $animalData['animal_id'],
                        'catalog_item_id' => $catalogItem->id,
                        'service_name_snapshot' => $catalogItem->name,
                        'quantity' => $quantity,
                        'base_price' => round((float) $catalogItem->current_price, 2),
                        'price_at_budget' => $price,
                        'tax_at_budget' => 0,
                        'subtotal' => $subtotal,
                        'position' => $itemPosition,
                    ]);
                }

                $budgetAnimal->update(['subtotal' => round($animalTotal, 2)]);
                $total += $animalTotal;
            }

            $budget->update(['subtotal' => round($total, 2), 'total' => round($total, 2)]);
            return $budget;
        });

        return response()->json(['data' => $this->serializeDetail($budget->load(['customer', 'animals.animal', 'animals.items.catalogItem']))], 201);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([
                Budget::STATUS_DRAFT,
                Budget::STATUS_SENT,
                Budget::STATUS_ACCEPTED,
                Budget::STATUS_REJECTED,
                Budget::STATUS_EXPIRED,
                Budget::STATUS_CONVERTED,
            ])],
            'customer_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'since' => ['nullable', 'date'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $budgets = Budget::query()
            ->with(['customer:id,tenant_id,name,last_name,email,phone', 'note:id,budget_id'])
            ->withCount(['animals', 'items'])
            ->where('tenant_id', $tenantId)
            ->when(isset($data['q']), function (Builder $query) use ($data) {
                $term = trim($data['q']);

                $query->where(function (Builder $query) use ($term) {
                    $query->where('folio', 'like', "%{$term}%")
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($term) {
                            $customerQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                        });
                });
            })
            ->when(isset($data['status']), fn (Builder $query) => $query->where('status', $data['status']))
            ->when(isset($data['customer_id']), fn (Builder $query) => $query->where('customer_id', $data['customer_id']))
            ->when(isset($data['since']), function (Builder $query) use ($data) {
                $query->where('updated_at', '>=', $data['since']);
            })
            ->latest('budget_date')
            ->latest('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $budgets->getCollection()
                ->map(fn (Budget $budget) => $this->serializeSummary($budget))
                ->values(),
            'meta' => [
                'current_page' => $budgets->currentPage(),
                'last_page' => $budgets->lastPage(),
                'per_page' => $budgets->perPage(),
                'total' => $budgets->total(),
            ],
        ]);
    }

    public function show(Request $request, Budget $budget)
    {
        abort_if($budget->tenant_id !== $request->user()->tenant_id, 404);

        $budget->load([
            'customer:id,tenant_id,name,last_name,email,phone',
            'note:id,budget_id',
            'animals' => fn ($query) => $query->orderBy('position')->with([
                'animal:id,tenant_id,name',
                'items' => fn ($items) => $items->orderBy('position')->with('catalogItem:id,name,type'),
            ]),
        ]);

        return response()->json([
            'data' => $this->serializeDetail($budget),
        ]);
    }

    public function destroy(Request $request, Budget $budget)
    {
        abort_unless($budget->tenant_id === $request->user()->tenant_id, 404);

        if ($budget->status !== Budget::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Solo se pueden eliminar presupuestos en borrador.',
            ], 409);
        }

        $budget->delete();

        return response()->json(['message' => 'Presupuesto eliminado correctamente.']);
    }

    public function convert(Request $request, Budget $budget, InventoryService $inventoryService)
    {
        abort_unless($budget->tenant_id === $request->user()->tenant_id, 404);

        $note = DB::transaction(function () use ($budget, $request, $inventoryService) {
            $budget = Budget::query()
                ->whereKey($budget->id)
                ->where('tenant_id', $request->user()->tenant_id)
                ->with(['animals.items'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($budget->status !== Budget::STATUS_DRAFT || $budget->note()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'budget' => 'Este presupuesto ya no puede convertirse en una venta.',
                ]);
            }

            $itemsForInventory = $budget->animals
                ->flatMap(fn ($animal) => $animal->items)
                ->groupBy('catalog_item_id')
                ->map(fn ($items, $catalogItemId) => [
                    'id' => (int) $catalogItemId,
                    'quantity' => (float) $items->sum('quantity'),
                ])->values()->all();

            $lastNoteId = Note::withTrashed()
                ->where('tenant_id', $budget->tenant_id)
                ->lockForUpdate()
                ->max('id') ?? 0;

            $note = Note::create([
                'tenant_id' => $budget->tenant_id,
                'synced_from_mobile' => true,
                'customer_id' => $budget->customer_id,
                'budget_id' => $budget->id,
                'folio' => 'VT-' . str_pad((string) ($lastNoteId + 1), 5, '0', STR_PAD_LEFT),
                'total' => $budget->total,
                'status' => 'PENDIENTE',
                'date_at' => now()->toDateString(),
            ]);

            $inventoryService->consumeForSale($request->user()->tenant, $itemsForInventory, 1, $note);

            foreach ($budget->animals as $budgetAnimal) {
                foreach ($budgetAnimal->items as $budgetItem) {
                    $note->details()->create([
                        'tenant_id' => $budget->tenant_id,
                        'catalog_item_id' => $budgetItem->catalog_item_id,
                        'animal_id' => $budgetAnimal->animal_id,
                        'quantity' => $budgetItem->quantity,
                        'price_at_sale' => $budgetItem->price_at_budget,
                        'tax_at_sale' => $budgetItem->tax_at_budget,
                        'subtotal' => $budgetItem->subtotal,
                    ]);
                }
            }

            $budget->update(['status' => Budget::STATUS_CONVERTED]);

            return $note;
        });

        app(PortalNotificationService::class)->notePublished($note->fresh(['customer', 'details']));

        return response()->json([
            'data' => [
                'id' => $note->id,
                'folio' => $note->folio,
                'total' => $note->total,
                'status' => $note->status,
                'budget_id' => $note->budget_id,
            ],
        ], 201);
    }

    private function serializeSummary(Budget $budget): array
    {
        return [
            'id' => $budget->id,
            'folio' => $budget->folio,
            'status' => $budget->status,
            'budget_date' => $budget->budget_date?->toDateString(),
            'valid_until' => $budget->valid_until?->toDateString(),
            'subtotal' => $budget->subtotal,
            'discount_total' => $budget->discount_total,
            'tax_total' => $budget->tax_total,
            'total' => $budget->total,
            'animals_count' => (int) $budget->animals_count,
            'items_count' => (int) $budget->items_count,
            'customer' => $this->serializeCustomer($budget->customer),
            'note_id' => $budget->note?->id,
            'created_at' => $budget->created_at?->toISOString(),
            'updated_at' => $budget->updated_at?->toISOString(),
        ];
    }

    private function serializeDetail(Budget $budget): array
    {
        return [
            'id' => $budget->id,
            'folio' => $budget->folio,
            'status' => $budget->status,
            'budget_date' => $budget->budget_date?->toDateString(),
            'valid_until' => $budget->valid_until?->toDateString(),
            'notes' => $budget->notes,
            'subtotal' => $budget->subtotal,
            'discount_total' => $budget->discount_total,
            'tax_total' => $budget->tax_total,
            'total' => $budget->total,
            'customer' => $this->serializeCustomer($budget->customer),
            'note_id' => $budget->note?->id,
            'animals' => $budget->animals->map(fn ($budgetAnimal) => [
                'id' => $budgetAnimal->id,
                'animal_id' => $budgetAnimal->animal_id,
                'name' => $budgetAnimal->animal?->name,
                'notes' => $budgetAnimal->notes,
                'subtotal' => $budgetAnimal->subtotal,
                'items' => $budgetAnimal->items->map(fn ($item) => [
                    'id' => $item->id,
                    'catalog_item_id' => $item->catalog_item_id,
                    'name' => $item->service_name_snapshot ?: $item->catalogItem?->name,
                    'type' => $item->catalogItem?->type,
                    'quantity' => $item->quantity,
                    'base_price' => $item->base_price,
                    'price_at_budget' => $item->price_at_budget,
                    'tax_at_budget' => $item->tax_at_budget,
                    'subtotal' => $item->subtotal,
                    'notes' => $item->notes,
                    'position' => (int) $item->position,
                ])->values(),
            ])->values(),
            'created_at' => $budget->created_at?->toISOString(),
            'updated_at' => $budget->updated_at?->toISOString(),
        ];
    }

    private function serializeCustomer($customer): ?array
    {
        if (! $customer) {
            return null;
        }

        return [
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];
    }

    private function budgetSigningProfile(Request $request, Budget $budget): ?VeterinarianProfile
    {
        $currentUserProfile = $request->user()?->veterinarianProfile;

        if ($currentUserProfile?->tenant_id === $budget->tenant_id
            && $currentUserProfile->is_active
            && $currentUserProfile->signature_path) {
            return $currentUserProfile;
        }

        return VeterinarianProfile::query()
            ->where('tenant_id', $budget->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('signature_path')
            ->orderBy('id')
            ->first();
    }
}
