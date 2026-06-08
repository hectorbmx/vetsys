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
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();

        $table->foreignId('tenant_id')
            ->constrained('tenants')
            ->cascadeOnDelete();

        $table->foreignId('tenant_billing_profile_id')
            ->nullable()
            ->constrained('tenant_billing_profiles')
            ->nullOnDelete();

        $table->foreignId('customer_id')
            ->constrained('customers')
            ->cascadeOnDelete();

        $table->foreignId('customer_tax_profile_id')
            ->nullable()
            ->constrained('customer_tax_profiles')
            ->nullOnDelete();

        $table->foreignId('note_id')
            ->constrained('notes')
            ->cascadeOnDelete();

        // Facturapi
        $table->string('facturapi_invoice_id')->nullable();
        $table->string('uuid')->nullable();

        // Folios internos/fiscales
        $table->string('series')->nullable();
        $table->string('folio')->nullable();

        // Estado interno
        $table->enum('status', [
            'draft',
            'issued',
            'cancelled',
            'error',
        ])->default('draft');

        // CFDI
        $table->string('cfdi_type', 10)->default('I');
        $table->string('cfdi_use', 5)->nullable();
        $table->string('payment_form', 5)->nullable();
        $table->string('payment_method', 5)->default('PUE');

        // Importes snapshot
        $table->decimal('subtotal', 12, 2)->default(0);
        $table->decimal('tax_total', 12, 2)->default(0);
        $table->decimal('total', 12, 2)->default(0);

        // Archivos
        $table->string('pdf_path')->nullable();
        $table->string('xml_path')->nullable();

        // Errores / respuesta API
        $table->text('error_message')->nullable();
        $table->json('facturapi_response')->nullable();

        // Fechas fiscales
        $table->timestamp('issued_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();

        $table->timestamps();

        $table->index(['tenant_id', 'status']);
        $table->index(['tenant_id', 'note_id']);
        $table->index('uuid');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
