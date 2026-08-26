<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\Club;
use App\Models\ClubNote;
use App\Models\Inventory;
use App\Services\InventoryService;
use App\Services\RichTextSanitizer;
use App\Services\TenantDocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubServiceNoteController extends Controller
{
    public function create(Club $club)
    {
        $tenant = auth()->user()->tenant;
        abort_unless($club->tenant_id === $tenant->id, 404);

        return view('client.clubes.services.create', [
            'club' => $club,
            'clubNote' => null,
            'initialSaleState' => null,
            'usesMonthlyCutoffBilling' => $tenant->usesMonthlyCutoffBilling(),
        ]);
    }

    public function store(Request $request, Club $club)
    {
        $tenant = auth()->user()->tenant;
        abort_unless($club->tenant_id === $tenant->id, 404);

        $data = $this->validateClubNoteRequest($request, $tenant->id);

        $clubNote = DB::transaction(function () use ($data, $tenant, $club) {
            $items = $this->normalizedItems($data['items']);
            $total = $items->sum(fn (array $item) => $item['quantity'] * $item['price']);
            $folio = $this->nextFolio($tenant->id);

            $clubNote = ClubNote::create([
                'tenant_id' => $tenant->id,
                'club_id' => $club->id,
                'folio' => $folio,
                'total' => $total,
                'status' => 'PENDIENTE',
                'date_at' => $data['date_at'],
                'notes_html' => $data['notes_html'],
            ]);

            $this->consumeInventory($tenant, $items->all(), $clubNote);
            $this->syncDetails($clubNote, $tenant->id, $items);

            return $clubNote;
        });

        return redirect()
            ->route('client.clubes.edit', ['clube' => $club, 'tab' => 'servicios'])
            ->with('success', "Nota de club {$clubNote->folio} generada correctamente.");
    }

    public function edit(Club $club, ClubNote $clubNote)
    {
        $tenant = auth()->user()->tenant;
        abort_unless($club->tenant_id === $tenant->id && $clubNote->tenant_id === $tenant->id && $clubNote->club_id === $club->id, 404);

        $clubNote->load('details.catalogItem.inventory');

        return view('client.clubes.services.create', [
            'club' => $club,
            'clubNote' => $clubNote,
            'initialSaleState' => $this->initialSaleStateFromClubNote($clubNote),
            'usesMonthlyCutoffBilling' => $tenant->usesMonthlyCutoffBilling(),
        ]);
    }

    public function update(Request $request, Club $club, ClubNote $clubNote)
    {
        $tenant = auth()->user()->tenant;
        abort_unless($club->tenant_id === $tenant->id && $clubNote->tenant_id === $tenant->id && $clubNote->club_id === $club->id, 404);

        $data = $this->validateClubNoteRequest($request, $tenant->id);

        DB::transaction(function () use ($data, $tenant, $clubNote) {
            $clubNote->load('details');
            $reversalSuffix = ':update:' . uniqid();
            $this->reverseInventory($tenant, $clubNote, $reversalSuffix);

            $items = $this->normalizedItems($data['items']);
            $total = $items->sum(fn (array $item) => $item['quantity'] * $item['price']);

            $clubNote->details()->delete();
            $clubNote->update([
                'total' => $total,
                'status' => 'PENDIENTE',
                'date_at' => $data['date_at'],
                'notes_html' => $data['notes_html'],
            ]);

            $this->consumeInventory($tenant, $items->all(), $clubNote, ':update:' . uniqid());
            $this->syncDetails($clubNote, $tenant->id, $items);
        });

        return redirect()
            ->route('client.clubes.edit', ['clube' => $club, 'tab' => 'servicios'])
            ->with('success', "Nota de club {$clubNote->folio} actualizada correctamente.");
    }

    public function ticket(Club $club, ClubNote $clubNote)
    {
        $tenant = auth()->user()->tenant;
        abort_unless($club->tenant_id === $tenant->id && $clubNote->tenant_id === $tenant->id && $clubNote->club_id === $club->id, 404);

        $clubNote->load(['details.catalogItem', 'club']);

        return view('client.clubes.services.ticket', [
            'club' => $club,
            'clubNote' => $clubNote,
            'tenant' => $tenant,
        ]);
    }

    public function destroy(Club $club, ClubNote $clubNote)
    {
        $tenant = auth()->user()->tenant;
        abort_unless($club->tenant_id === $tenant->id && $clubNote->tenant_id === $tenant->id && $clubNote->club_id === $club->id, 404);
        $this->abortIfClubNoteHasFinancialMovements($clubNote);

        DB::transaction(function () use ($tenant, $clubNote) {
            $clubNote->load('details');
            $this->reverseInventory($tenant, $clubNote, ':delete');
            $clubNote->details()->delete();
            $clubNote->delete();
        });

        return redirect()
            ->route('client.clubes.edit', ['clube' => $club, 'tab' => 'servicios'])
            ->with('success', "Nota de club {$clubNote->folio} eliminada correctamente.");
    }

    public function pdf(Club $club, ClubNote $clubNote, TenantDocumentTemplateService $templateService)
    {
        $tenant = auth()->user()->tenant;
        abort_unless($club->tenant_id === $tenant->id && $clubNote->tenant_id === $tenant->id && $clubNote->club_id === $club->id, 404);

        $clubNote->load(['details.catalogItem', 'club', 'tenant']);

        $templateValues = [
            'club_name' => $club->name,
            'club_note_folio' => $clubNote->folio,
            'club_note_date' => $clubNote->date_at?->format('d/m/Y') ?? '',
            'club_note_total' => '$' . number_format((float) $clubNote->total, 2),
            'services_count' => (string) $clubNote->details->count(),
            'clinic_name' => $tenant->business_name ?: $tenant->name,
        ];

        $template = $templateService->forTenantAndType($tenant->id, TenantDocumentTemplateService::CLUB_NOTE);
        $documentTemplate = [
            'body_html' => $templateService->render(TenantDocumentTemplateService::CLUB_NOTE, $template['body_html'], $templateValues),
            'header_color' => $template['header_color'],
            'closing_text' => $templateService->renderPlainText(TenantDocumentTemplateService::CLUB_NOTE, $template['closing_text'], $templateValues),
            'image_section_title' => $templateService->renderPlainText(TenantDocumentTemplateService::CLUB_NOTE, $template['image_section_title'], $templateValues),
        ];

        $pdf = Pdf::loadView('client.clubes.services.club-note-pdf', [
            'club' => $club,
            'clubNote' => $clubNote,
            'tenant' => $tenant,
            'logoUrl' => $tenant?->logoUrl(),
            'documentTemplate' => $documentTemplate,
        ])
            ->setPaper('letter', 'portrait')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'nota-club-' . str($clubNote->folio)->slug() . '.pdf';
        $response = $pdf->stream($filename);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function validateClubNoteRequest(Request $request, int $tenantId): array
    {
        $data = $request->validate([
            'date_at' => ['required', 'date'],
            'notes_html' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $data['notes_html'] = app(RichTextSanitizer::class)->sanitize($data['notes_html'] ?? '');

        return $data;
    }

    private function normalizedItems(array $items)
    {
        return collect($items)
            ->map(fn (array $item) => [
                'id' => (int) $item['id'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
            ])
            ->values();
    }

    private function nextFolio(int $tenantId): string
    {
        $lastId = ClubNote::withTrashed()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('id') ?? 0;

        $sequence = $lastId + 1;

        do {
            $folio = 'CLUB-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ClubNote::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('folio', $folio)
            ->exists());

        return $folio;
    }

    private function syncDetails(ClubNote $clubNote, int $tenantId, $items): void
    {
        foreach ($items as $item) {
            $clubNote->details()->create([
                'tenant_id' => $tenantId,
                'catalog_item_id' => $item['id'],
                'quantity' => $item['quantity'],
                'price_at_sale' => $item['price'],
                'subtotal' => $item['quantity'] * $item['price'],
            ]);
        }
    }

    private function consumeInventory($tenant, array $items, ClubNote $clubNote, string $idempotencySuffix = ''): void
    {
        $requestedByItem = collect($items)
            ->groupBy(fn (array $item) => (int) $item['id'])
            ->map(fn ($rows) => $rows->sum(fn (array $item) => (float) $item['quantity']));

        $catalogItems = CatalogItem::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $requestedByItem->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $inventories = Inventory::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('catalog_item_id', $requestedByItem->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('catalog_item_id');

        foreach ($requestedByItem as $catalogItemId => $quantity) {
            $catalogItem = $catalogItems->get($catalogItemId);

            if (! $catalogItem?->has_inventory) {
                continue;
            }

            $inventory = $inventories->get($catalogItemId);
            if (! $inventory) {
                throw ValidationException::withMessages([
                    'items' => "El producto {$catalogItem->name} controla inventario, pero no tiene existencias configuradas.",
                ]);
            }

            app(InventoryService::class)->recordMovement(
                $tenant,
                $inventory,
                'sale',
                (float) $quantity,
                $clubNote,
                auth()->id(),
                'Venta de club',
                "Descuento automatico por nota de club {$clubNote->folio}.",
                "club-sale:{$clubNote->id}:catalog-item:{$catalogItemId}{$idempotencySuffix}"
            );
        }
    }

    private function reverseInventory($tenant, ClubNote $clubNote, string $idempotencySuffix = ''): void
    {
        $quantities = $clubNote->details()
            ->where('tenant_id', $tenant->id)
            ->with('catalogItem.inventory')
            ->get()
            ->groupBy('catalog_item_id')
            ->map(fn ($details) => $details->sum(fn ($detail) => (float) $detail->quantity));

        foreach ($quantities as $catalogItemId => $quantity) {
            $catalogItem = CatalogItem::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($catalogItemId)
                ->with('inventory')
                ->first();

            if (! $catalogItem?->has_inventory || ! $catalogItem->inventory) {
                continue;
            }

            app(InventoryService::class)->recordMovement(
                $tenant,
                $catalogItem->inventory,
                'return',
                (float) $quantity,
                $clubNote,
                auth()->id(),
                'Reversion de venta de club',
                "Reversion automatica por ajuste de nota de club {$clubNote->folio}.",
                "club-sale-reversal:{$clubNote->id}:catalog-item:{$catalogItemId}{$idempotencySuffix}"
            );
        }
    }

    private function initialSaleStateFromClubNote(ClubNote $clubNote): array
    {
        return [
            'noteDate' => optional($clubNote->date_at)->format('Y-m-d'),
            'basket' => $clubNote->details
                ->map(function ($detail) {
                    $item = $detail->catalogItem;

                    return [
                        'id' => $detail->catalog_item_id,
                        'name' => $item?->name ?? 'Concepto eliminado',
                        'quantity' => (int) $detail->quantity,
                        'price' => (float) $detail->price_at_sale,
                        'has_inventory' => (bool) ($item?->has_inventory ?? false),
                        'stock_actual' => (float) ($item?->inventory?->stock_actual ?? 0) + (float) (($item?->has_inventory ?? false) ? $detail->quantity : 0),
                        'stock_minimo' => (float) ($item?->inventory?->stock_minimo ?? 0),
                        'allow_negative_stock' => (bool) ($item?->inventory?->allow_negative_stock ?? false),
                    ];
                })
                ->values(),
        ];
    }

    private function abortIfClubNoteHasFinancialMovements(ClubNote $clubNote): void
    {
        // Extension point: block deletion/editing here once club payments or club statements exist.
    }
}
