<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetAnimal;
use App\Models\BudgetItem;
use App\Models\CatalogItem;
use App\Models\VeterinarianProfile;
use App\Services\DocumentPresentationService;
use App\Services\TenantDocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $search = trim((string) $request->get('q', ''));
        $allowedPerPage = [15, 30, 50, 100];
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, $allowedPerPage, true)
            ? $requestedPerPage
            : 15;

        $budgets = $tenant->budgets()
            ->with('customer')
            ->withCount(['animals', 'items'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('last_name', 'LIKE', "%{$search}%")
                                ->orWhere('phone', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        });
                });
            })
            ->latest('budget_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $monthBudgets = $tenant->budgets()
            ->whereBetween('budget_date', [$startOfMonth, $endOfMonth]);

        $totalBudgetsMonth = (clone $monthBudgets)->count();
        $acceptedBudgetsMonth = (clone $monthBudgets)->where('status', Budget::STATUS_ACCEPTED)->count();
        $openBudgets = $tenant->budgets()
            ->whereIn('status', [Budget::STATUS_DRAFT, Budget::STATUS_SENT])
            ->count();
        $acceptedTotalMonth = (float) (clone $monthBudgets)
            ->where('status', Budget::STATUS_ACCEPTED)
            ->sum('total');
        $expiringSoon = $tenant->budgets()
            ->whereIn('status', [Budget::STATUS_DRAFT, Budget::STATUS_SENT])
            ->whereDate('valid_until', '>=', today())
            ->whereDate('valid_until', '<=', today()->addDays(7))
            ->count();

        $statusLabels = [
            Budget::STATUS_DRAFT => 'Borrador',
            Budget::STATUS_SENT => 'Enviado',
            Budget::STATUS_ACCEPTED => 'Aceptado',
            Budget::STATUS_REJECTED => 'Rechazado',
            Budget::STATUS_EXPIRED => 'Expirado',
        ];

        return view('client.budgets.index', compact(
            'budgets',
            'perPage',
            'search',
            'totalBudgetsMonth',
            'acceptedBudgetsMonth',
            'openBudgets',
            'acceptedTotalMonth',
            'expiringSoon',
            'statusLabels'
        ));
    }

    public function create()
    {
        return view('client.budgets.create');
    }

    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'budget_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:budget_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'animals' => ['required', 'array', 'min:1'],
            'animals.*.animal_id' => ['required', 'integer'],
            'animals.*.notes' => ['nullable', 'string', 'max:2000'],
            'animals.*.items' => ['required', 'array', 'min:1'],
            'animals.*.items.*.catalog_item_id' => ['required', 'integer'],
            'animals.*.items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'animals.*.items.*.price_at_budget' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'animals.*.items.*.service_name_snapshot' => ['nullable', 'string', 'max:255'],
        ]);
        $data['notes'] = $this->sanitizeBudgetRichText($data['notes'] ?? null);

        $customer = $tenant->customers()->whereKey($data['customer_id'])->firstOrFail();
        $animalIds = collect($data['animals'])->pluck('animal_id')->map(fn ($id) => (int) $id)->unique()->values();
        $validAnimalIds = $customer->animals()
            ->whereIn('id', $animalIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($validAnimalIds->count() !== $animalIds->count()) {
            return back()
                ->withInput()
                ->withErrors(['animals' => 'Uno o mas caballos no pertenecen al cliente seleccionado.']);
        }

        $catalogItemIds = collect($data['animals'])
            ->flatMap(fn ($animal) => collect($animal['items'])->pluck('catalog_item_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $catalogItems = $tenant->catalogItems()
            ->whereIn('id', $catalogItemIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($catalogItems->count() !== $catalogItemIds->count()) {
            return back()
                ->withInput()
                ->withErrors(['animals' => 'Uno o mas servicios no pertenecen al catalogo activo del tenant.']);
        }

        $budget = DB::transaction(function () use ($tenant, $data, $catalogItems) {
            $folioSequence = (Budget::withTrashed()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->max('id') ?? 0) + 1;

            do {
                $folio = 'PRES-' . str_pad($folioSequence, 5, '0', STR_PAD_LEFT);
                $folioSequence++;
            } while (Budget::withTrashed()
                ->where('tenant_id', $tenant->id)
                ->where('folio', $folio)
                ->exists());

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

            $budgetTotal = 0;

            foreach (array_values($data['animals']) as $animalPosition => $animalData) {
                $budgetAnimal = $budget->animals()->create([
                    'tenant_id' => $tenant->id,
                    'animal_id' => $animalData['animal_id'],
                    'position' => $animalPosition,
                    'notes' => $animalData['notes'] ?? null,
                    'subtotal' => 0,
                ]);

                $animalSubtotal = 0;

                foreach (array_values($animalData['items']) as $itemPosition => $itemData) {
                    $catalogItem = $catalogItems->get((int) $itemData['catalog_item_id']);
                    $quantity = round((float) $itemData['quantity'], 2);
                    $priceAtBudget = round((float) $itemData['price_at_budget'], 2);
                    $basePrice = round((float) $catalogItem->current_price, 2);
                    $subtotal = round($quantity * $priceAtBudget, 2);
                    $animalSubtotal += $subtotal;

                    $budgetAnimal->items()->create([
                        'tenant_id' => $tenant->id,
                        'budget_id' => $budget->id,
                        'animal_id' => $animalData['animal_id'],
                        'catalog_item_id' => $catalogItem->id,
                        'service_name_snapshot' => $catalogItem->name,
                        'quantity' => $quantity,
                        'base_price' => $basePrice,
                        'price_at_budget' => $priceAtBudget,
                        'tax_at_budget' => 0,
                        'subtotal' => $subtotal,
                        'notes' => null,
                        'position' => $itemPosition,
                    ]);
                }

                $budgetAnimal->update([
                    'subtotal' => round($animalSubtotal, 2),
                ]);

                $budgetTotal += $animalSubtotal;
            }

            $budget->update([
                'subtotal' => round($budgetTotal, 2),
                'total' => round($budgetTotal, 2),
            ]);

            return $budget;
        });

        return redirect()
            ->route('client.budgets.show', $budget)
            ->with('success', "Presupuesto {$budget->folio} guardado correctamente.");
    }

    public function show(Budget $budget)
    {
        abort_unless($budget->tenant_id === auth()->user()->tenant_id, 404);

        $budget->load(['customer', 'animals.animal', 'animals.items.catalogItem']);

        $statusLabels = [
            Budget::STATUS_DRAFT => 'Borrador',
            Budget::STATUS_SENT => 'Enviado',
            Budget::STATUS_ACCEPTED => 'Aceptado',
            Budget::STATUS_REJECTED => 'Rechazado',
            Budget::STATUS_EXPIRED => 'Expirado',
        ];

        return view('client.budgets.show', compact('budget', 'statusLabels'));
    }

    public function edit(Budget $budget)
    {
        abort_unless($budget->tenant_id === auth()->user()->tenant_id, 404);

        return redirect()->route('client.budgets.show', $budget);
    }

    public function update(Request $request, Budget $budget)
    {
        abort_unless($budget->tenant_id === auth()->user()->tenant_id, 404);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $budget->update([
            'notes' => $this->sanitizeBudgetRichText($data['notes'] ?? null),
        ]);

        return redirect()
            ->route('client.budgets.show', $budget)
            ->with('success', 'Notas del presupuesto actualizadas correctamente.');
    }

    public function destroy(Budget $budget)
    {
        abort_unless($budget->tenant_id === auth()->user()->tenant_id, 404);

        $budget->delete();

        return redirect()
            ->route('client.budgets.index')
            ->with('success', "Presupuesto {$budget->folio} eliminado correctamente.");
    }

    public function searchCustomers(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $customers = auth()->user()->tenant->customers()
            ->where('status', 'active')
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            })
            ->withCount('animals')
            ->take(10)
            ->get()
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'animals_count' => $customer->animals_count,
            ]);

        return response()->json($customers);
    }

    public function customerAnimals(Request $request, int $customer)
    {
        $tenant = auth()->user()->tenant;

        $customerModel = $tenant->customers()
            ->whereKey($customer)
            ->firstOrFail();

        $animals = $customerModel->animals()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'customer_id', 'name', 'status'])
            ->map(fn ($animal) => [
                'id' => $animal->id,
                'name' => $animal->name,
                'status' => $animal->status,
            ]);

        return response()->json($animals);
    }

    public function searchServices(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $items = auth()->user()->tenant->catalogItems()
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            })
            ->orderBy('name')
            ->take(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'type' => $item->type,
                'price' => $item->current_price,
            ]);

        return response()->json($items);
    }

    public function storeItem(Request $request, Budget $budget, BudgetAnimal $budgetAnimal)
    {
        $tenant = auth()->user()->tenant;

        $this->authorizeBudgetAnimal($budget, $budgetAnimal);

        $data = $request->validate([
            'catalog_item_id' => [
                'required',
                'integer',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)),
            ],
        ]);

        $catalogItem = $tenant->catalogItems()->whereKey($data['catalog_item_id'])->firstOrFail();
        $quantity = 1.00;
        $priceAtBudget = round((float) $catalogItem->current_price, 2);

        DB::transaction(function () use ($tenant, $budget, $budgetAnimal, $catalogItem, $quantity, $priceAtBudget) {
            $budgetAnimal->items()->create([
                'tenant_id' => $tenant->id,
                'budget_id' => $budget->id,
                'animal_id' => $budgetAnimal->animal_id,
                'catalog_item_id' => $catalogItem->id,
                'service_name_snapshot' => $catalogItem->name,
                'quantity' => $quantity,
                'base_price' => round((float) $catalogItem->current_price, 2),
                'price_at_budget' => $priceAtBudget,
                'tax_at_budget' => 0,
                'subtotal' => round($quantity * $priceAtBudget, 2),
                'position' => ($budgetAnimal->items()->max('position') ?? -1) + 1,
            ]);

            $this->recalculateBudget($budget);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Servicio agregado al presupuesto correctamente.',
            ]);
        }

        return redirect()
            ->route('client.budgets.show', $budget)
            ->with('success', 'Servicio agregado al presupuesto correctamente.');
    }

    public function updateItem(Request $request, Budget $budget, BudgetItem $budgetItem)
    {
        $this->authorizeBudgetItem($budget, $budgetItem);

        $data = $request->validate([
            'price_at_budget' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($budget, $budgetItem, $data) {
            $quantity = round((float) $budgetItem->quantity, 2);
            $priceAtBudget = round((float) $data['price_at_budget'], 2);

            $budgetItem->update([
                'price_at_budget' => $priceAtBudget,
                'subtotal' => round($quantity * $priceAtBudget, 2),
            ]);

            $this->recalculateBudget($budget);
        });

        $budgetItem->refresh();
        $budget->refresh();
        $budgetAnimal = $budgetItem->budgetAnimal()->first();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Precio actualizado correctamente.',
                'item' => [
                    'id' => $budgetItem->id,
                    'price_at_budget' => (float) $budgetItem->price_at_budget,
                    'subtotal' => (float) $budgetItem->subtotal,
                ],
                'budget_animal' => [
                    'id' => $budgetAnimal?->id,
                    'subtotal' => (float) ($budgetAnimal?->subtotal ?? 0),
                ],
                'budget' => [
                    'id' => $budget->id,
                    'total' => (float) $budget->total,
                ],
            ]);
        }

        return redirect()
            ->route('client.budgets.show', $budget)
            ->with('success', 'Precio actualizado correctamente.');
    }

    public function destroyItem(Budget $budget, BudgetItem $budgetItem)
    {
        $this->authorizeBudgetItem($budget, $budgetItem);

        DB::transaction(function () use ($budget, $budgetItem) {
            $budgetItem->delete();
            $this->recalculateBudget($budget);
        });

        return redirect()
            ->route('client.budgets.show', $budget)
            ->with('success', 'Partida eliminada del presupuesto correctamente.');
    }

    public function pdf(
        Budget $budget,
        TenantDocumentTemplateService $templateService,
        DocumentPresentationService $documentPresentationService
    )
    {
        abort_unless($budget->tenant_id === auth()->user()->tenant_id, 404);

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
        $signingProfile = $this->budgetSigningProfile($budget);
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

        $response = $pdf->stream($filename);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function authorizeBudgetAnimal(Budget $budget, BudgetAnimal $budgetAnimal): void
    {
        abort_unless($budget->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless($budgetAnimal->tenant_id === $budget->tenant_id && $budgetAnimal->budget_id === $budget->id, 404);
    }

    private function authorizeBudgetItem(Budget $budget, BudgetItem $budgetItem): void
    {
        abort_unless($budget->tenant_id === auth()->user()->tenant_id, 404);
        abort_unless($budgetItem->tenant_id === $budget->tenant_id && $budgetItem->budget_id === $budget->id, 404);
    }

    private function recalculateBudget(Budget $budget): void
    {
        $budget->load('animals.items');

        $budgetTotal = 0;

        foreach ($budget->animals as $budgetAnimal) {
            $animalSubtotal = round((float) $budgetAnimal->items->sum('subtotal'), 2);
            $budgetAnimal->update(['subtotal' => $animalSubtotal]);
            $budgetTotal += $animalSubtotal;
        }

        $budget->update([
            'subtotal' => round($budgetTotal, 2),
            'total' => round($budgetTotal, 2),
        ]);
    }

    private function sanitizeBudgetRichText(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ol><ul><li><blockquote><a><span>');
        $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace("/\s+on\w+='[^']*'/i", '', $html) ?? $html;
        $html = preg_replace('/href=("|\')\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? $html;
        $html = preg_replace_callback('/<span\b([^>]*)>/i', function ($matches) {
            $attributes = $matches[1] ?? '';

            $quillColorMap = [
                'ql-color-red' => '#e60000',
                'ql-color-orange' => '#f90',
                'ql-color-yellow' => '#ffff00',
                'ql-color-green' => '#008a00',
                'ql-color-blue' => '#06c',
                'ql-color-purple' => '#93f',
            ];

            if (preg_match('/class=("|\')([^"\']*)\1/i', $attributes, $classMatch)) {
                foreach (preg_split('/\s+/', trim($classMatch[2])) as $className) {
                    if (isset($quillColorMap[$className])) {
                        return '<span style="color: ' . $quillColorMap[$className] . ';">';
                    }
                }
            }

            if (preg_match('/style=("|\')([^"\']*)\1/i', $attributes, $styleMatch)) {
                $style = $styleMatch[2];

                if (preg_match('/(?:^|;)\s*color\s*:\s*(#[0-9a-f]{3,6}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)|[a-z]+)\s*(?:;|$)/i', $style, $colorMatch)) {
                    return '<span style="color: ' . e($colorMatch[1]) . ';">';
                }
            }

            return '<span>';
        }, $html) ?? $html;

        return trim($html) === '<p><br></p>' ? null : trim($html);
    }

    private function budgetSigningProfile(Budget $budget): ?VeterinarianProfile
    {
        $currentUserProfile = auth()->user()?->veterinarianProfile;

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
