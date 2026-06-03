<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('catalog_item_id')->unique()->constrained()->onDelete('cascade');
            
            $table->decimal('stock_actual', 12, 2)->default(0.00);
            $table->decimal('stock_minimo', 12, 2)->default(0.00); // Para alertas de escasez
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};