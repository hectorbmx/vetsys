<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            
            $table->string('folio'); // Folio interno de la venta (ej: VT-0001)
            $table->decimal('total', 12, 2)->default(0.00);
            $table->enum('status', ['PENDIENTE', 'PAGADA', 'CANCELADA'])->default('PENDIENTE');
            $table->date('date_at'); // Fecha de la nota elegida por el usuario
            
            $table->timestamps();
            $table->softDeletes();

            // Aseguramos que el folio sea único solo dentro de la misma veterinaria
            $table->unique(['tenant_id', 'folio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};