<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('catalog_item_id')->constrained()->onDelete('cascade');
            
            $table->decimal('price', 12, 2);
            $table->timestamp('start_date')->useCurrent();
            $table->timestamp('end_date')->nullable(); // Null significa que es el precio vigente actual
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};