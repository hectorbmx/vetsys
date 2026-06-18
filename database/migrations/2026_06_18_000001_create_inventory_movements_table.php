<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('direction', 8);
            $table->decimal('quantity', 12, 2);
            $table->decimal('stock_before', 12, 2);
            $table->decimal('stock_after', 12, 2);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'catalog_item_id', 'occurred_at'], 'inventory_movements_item_date_idx');
            $table->index(['reference_type', 'reference_id'], 'inventory_movements_reference_idx');
            $table->unique(['tenant_id', 'idempotency_key'], 'inventory_movements_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
