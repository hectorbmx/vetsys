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
    Schema::create('customer_tax_profiles', function (Blueprint $table) {
        $table->id();

        $table->foreignId('tenant_id')
            ->constrained('tenants')
            ->cascadeOnDelete();

        $table->foreignId('customer_id')
            ->constrained('customers')
            ->cascadeOnDelete();

        // Facturapi
        $table->string('facturapi_customer_id')->nullable();

        // Datos fiscales del receptor
        $table->string('legal_name');
        $table->string('tax_id', 13);
        $table->string('tax_system', 3);
        $table->string('zip', 5);
        $table->string('email')->nullable();

        // Uso CFDI por defecto
        $table->string('cfdi_use', 5)->default('G03');

        // Perfil principal del cliente
        $table->boolean('is_default')->default(false);

        $table->timestamps();

        $table->index(['tenant_id', 'customer_id']);
        $table->index('tax_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_tax_profiles');
    }
};
