<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 96)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('MXN');
            $table->string('status')->default('pending')->index();
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_links');
    }
};
