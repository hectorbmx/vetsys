<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name_snapshot');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('price_at_budget', 12, 2)->default(0);
            $table->decimal('tax_at_budget', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'budget_id']);
            $table->index(['tenant_id', 'budget_animal_id']);
            $table->index(['tenant_id', 'animal_id']);
            $table->index(['tenant_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
