<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('note_id')->constrained()->onDelete('cascade');
            $table->foreignId('catalog_item_id')->constrained()->onDelete('cascade');
            
            // Relación opcional con la mascota (Por si es un producto general)
            $table->foreignId('animal_id')->nullable()->constrained()->onDelete('set null');
            
            $table->decimal('quantity', 10, 2);
            $table->decimal('price_at_sale', 12, 2); // Precio congelado editable por el usuario
            $table->decimal('tax_at_sale', 5, 2)->default(0.00); // Preparado para IVA futuro
            $table->decimal('subtotal', 12, 2);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_details');
    }
};