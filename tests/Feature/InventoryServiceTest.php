<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Inventory;
use App\Models\Note;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_blocks_a_sale_that_exceeds_stock_when_negative_stock_is_not_allowed(): void
    {
        [$tenant, $item, $inventory, $note] = $this->inventoryScenario(10, 2, false);

        try {
            app(InventoryService::class)->consumeForSale(
                $tenant,
                [['id' => $item->id, 'quantity' => 6]],
                2,
                $note
            );

            $this->fail('The inventory validation should have blocked the sale.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame('10.00', $inventory->fresh()->stock_actual);
        $this->assertSame(
            0,
            TenantNotification::where('tenant_id', $tenant->id)->count()
        );
    }

    public function test_it_allows_negative_stock_and_creates_a_notification_when_configured(): void
    {
        [$tenant, $item, $inventory, $note] = $this->inventoryScenario(10, 2, true);

        app(InventoryService::class)->consumeForSale(
            $tenant,
            [['id' => $item->id, 'quantity' => 6]],
            2,
            $note
        );

        $this->assertSame('-2.00', $inventory->fresh()->stock_actual);
        $this->assertDatabaseHas('tenant_notifications', [
            'tenant_id' => $tenant->id,
            'type' => 'inventory.negative',
        ]);
    }

    public function test_it_notifies_when_a_sale_reaches_the_minimum_stock(): void
    {
        [$tenant, $item, $inventory, $note] = $this->inventoryScenario(10, 2, false);

        app(InventoryService::class)->consumeForSale(
            $tenant,
            [['id' => $item->id, 'quantity' => 8]],
            1,
            $note
        );

        $this->assertSame('2.00', $inventory->fresh()->stock_actual);
        $this->assertDatabaseHas('tenant_notifications', [
            'tenant_id' => $tenant->id,
            'type' => 'inventory.low',
        ]);
    }

    private function inventoryScenario(
        float $stock,
        float $minimum,
        bool $allowNegative
    ): array {
        $tenant = Tenant::create([
            'name' => 'Inventory Test Tenant',
            'slug' => 'inventory-test-' . uniqid(),
        ]);

        $item = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inventory Test Product',
            'sku' => 'TEST-' . uniqid(),
            'type' => 'product',
            'has_inventory' => true,
            'is_active' => true,
        ]);

        $inventory = Inventory::create([
            'tenant_id' => $tenant->id,
            'catalog_item_id' => $item->id,
            'stock_actual' => $stock,
            'stock_minimo' => $minimum,
            'allow_negative_stock' => $allowNegative,
        ]);

        $note = new Note(['folio' => 'VT-TEST']);
        $note->setAttribute('id', 999999);

        return [$tenant, $item, $inventory, $note];
    }
}
