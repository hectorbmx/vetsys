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
    Schema::create('tenant_billing_profiles', function (Blueprint $table) {
        $table->id();

        $table->foreignId('tenant_id')
            ->constrained('tenants')
            ->cascadeOnDelete();

        // Facturapi
        $table->string('facturapi_organization_id')->nullable();
        $table->text('facturapi_api_key')->nullable();

        // Datos fiscales del emisor
        $table->string('legal_name');
        $table->string('tax_id', 13);
        $table->string('tax_system', 3);
        $table->string('zip', 5);
        $table->string('email')->nullable();

        // Estado de configuración
        $table->boolean('csd_uploaded')->default(false);
        $table->boolean('is_active')->default(false);

        $table->timestamps();

        $table->unique('tenant_id');
        $table->index('tax_id');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_profiles');
    }
};
