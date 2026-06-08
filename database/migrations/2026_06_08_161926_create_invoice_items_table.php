<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('invoice_items', function (Blueprint $table) {
        $table->id();

        $table->foreignId('invoice_id')
            ->constrained('invoices')
            ->cascadeOnDelete();

        // Referencia opcional al detalle original
        $table->foreignId('note_detail_id')
            ->nullable()
            ->constrained('note_details')
            ->nullOnDelete();

        // Facturapi
        $table->string('facturapi_product_id')->nullable();

        // SAT
        $table->string('product_key', 20)->default('01010101');
        $table->string('unit_key', 10)->default('E48');

        // Concepto facturado
        $table->string('description');

        $table->decimal('quantity', 12, 4)->default(1);
        $table->decimal('unit_price', 12, 2)->default(0);

        $table->decimal('subtotal', 12, 2)->default(0);
        $table->decimal('tax_amount', 12, 2)->default(0);
        $table->decimal('total', 12, 2)->default(0);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
