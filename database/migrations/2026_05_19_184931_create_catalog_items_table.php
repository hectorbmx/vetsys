<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            $table->string('name');
            $table->string('sku')->nullable(); // Código de barras o identificador único
            $table->enum('type', ['product', 'service'])->default('service');
            $table->text('description')->nullable();
            
            // Preparado para el IVA en el futuro (Por defecto 0.00 % neto)
            $table->decimal('tax_percentage', 5, 2)->default(0.00); 
            
            $table->boolean('has_inventory')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();

            // Un mismo tenant no debe duplicar SKU o nombres idénticos
            $table->unique(['tenant_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};