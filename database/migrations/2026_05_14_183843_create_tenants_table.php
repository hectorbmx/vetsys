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
        Schema::create('tenants', function (Blueprint $table) {

            $table->id();

            // Datos generales
            $table->string('name');
            $table->string('slug')->unique();

            // Empresa
            $table->string('business_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Branding
            $table->string('logo')->nullable();

            // SaaS status
            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'cancelled'
            ])->default('active');

            // Plan actual
            $table->foreignId('plan_id')->nullable();

            // Stripe (futuro)
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();

            // Fechas importantes
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // Auditoría simple
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};