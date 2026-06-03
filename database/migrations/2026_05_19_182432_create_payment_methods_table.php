<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            // Conexión SaaS estricta
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            $table->string('name'); // Ej: "Efectivo", "Terminal Clip", "Transferencia STP"
            $table->string('slug'); // Ej: "efectivo", "terminal-clip", "transferencia-stp"
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // Por si quieren ocultar un método viejo sin romper históricos

            // Evitamos duplicados del mismo método en la misma clínica
            $table->unique(['tenant_id', 'slug']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};