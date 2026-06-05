<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogItemController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(['product', 'service'])],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $items = CatalogItem::withTrashed()
            ->with('inventory')
            ->where('tenant_id', $tenantId)
            ->when(isset($data['since']), function (Builder $query) use ($data) {
                $query->where(function (Builder $query) use ($data) {
                    $query->where('updated_at', '>=', $data['since'])
                        ->orWhere('deleted_at', '>=', $data['since']);
                });
            })
            ->when(isset($data['type']), fn (Builder $query) => $query->where('type', $data['type']))
            ->when(array_key_exists('active', $data), fn (Builder $query) => $query->where('is_active', $data['active']))
            ->when(isset($data['q']), function (Builder $query) use ($data) {
                $search = $data['q'];

                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $items->getCollection()->map(fn (CatalogItem $item) => $this->serializeItem($item)),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, CatalogItem $catalogItem)
    {
        abort_if($catalogItem->tenant_id !== $request->user()->tenant_id, 404);

        return response()->json([
            'data' => $this->serializeItem($catalogItem->load('inventory')),
        ]);
    }

    private function serializeItem(CatalogItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'type' => $item->type,
            'description' => $item->description,
            'tax_percentage' => $item->tax_percentage,
            'has_inventory' => $item->has_inventory,
            'is_active' => $item->is_active,
            'current_price' => $item->current_price,
            'stock_actual' => $item->inventory?->stock_actual,
            'stock_minimo' => $item->inventory?->stock_minimo,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
            'deleted_at' => $item->deleted_at?->toISOString(),
        ];
    }
}
