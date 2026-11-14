<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\CatalogItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuickCatalogService
{
    /** @return array{item: \App\Models\CatalogItem, created: bool, idempotent: bool} */
    public function createOrReuse(Tenant $tenant, string $name, float $price, ?string $clientUuid = null): array
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'El nombre del servicio es obligatorio.']);
        }

        return DB::transaction(function () use ($tenant, $name, $price, $clientUuid) {
            // Serialize quick creations for this tenant, including the initial SKU allocation.
            $lockedTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if ($clientUuid !== null) {
                $operation = DB::table('quick_service_operations')->where('tenant_id', $tenant->id)
                    ->where('client_uuid', $clientUuid)->first();
                if ($operation) {
                    if ($operation->requested_name !== $name || (int) round((float) $operation->requested_price * 100) !== (int) round($price * 100)) {
                        throw ValidationException::withMessages(['client_uuid' => 'Este identificador ya se utilizó con otros datos.']);
                    }
                    $item = CatalogItem::withTrashed()->where('tenant_id', $tenant->id)->find($operation->catalog_item_id);
                    if (! $item) {
                        throw ValidationException::withMessages(['client_uuid' => 'El servicio asociado ya no está disponible.']);
                    }
                    return ['item' => $item->load('inventory'), 'created' => (bool) $operation->created_item, 'idempotent' => true];
                }
            }
            $item = $this->findActiveService($lockedTenant, $name);
            $created = ! $item;
            if ($created) {
                $item = $lockedTenant->catalogItems()->create([
                'name' => $name,
                'sku' => $this->nextAvailableSku($tenant->id),
                'type' => 'service',
                'description' => null,
                'has_inventory' => false,
                'tax_percentage' => 0,
                'is_active' => true,
                ]);
                $item->priceHistories()->create([
                'tenant_id' => $tenant->id,
                'price' => $price,
                'start_date' => now(),
                'end_date' => null,
                ]);
            }

            if ($clientUuid !== null) {
                DB::table('quick_service_operations')->insert([
                    'tenant_id' => $tenant->id, 'client_uuid' => $clientUuid,
                    'catalog_item_id' => $item->id, 'requested_name' => $name,
                    'requested_price' => $price, 'created_item' => $created,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return ['item' => $item->load('inventory'), 'created' => $created, 'idempotent' => false];
        }, 3);
    }

    public function assertServiceNameAvailable(Tenant $tenant, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'El nombre del servicio es obligatorio.']);
        }
        if ($this->findActiveService($tenant, $name)) {
            throw ValidationException::withMessages(['name' => 'Ya existe un servicio activo con ese nombre.']);
        }
    }

    private function findActiveService(Tenant $tenant, string $name): ?CatalogItem
    {
        return $tenant->catalogItems()->where('type', 'service')->where('is_active', true)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->orderBy('id')->first();
    }

    private function nextAvailableSku(int $tenantId): string
    {
        $max = CatalogItem::withTrashed()->where('tenant_id', $tenantId)
            ->where('sku', 'like', 'SERV-%')->pluck('sku')
            ->map(fn ($sku) => preg_match('/^SERV-(\d+)$/', $sku, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return 'SERV-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
