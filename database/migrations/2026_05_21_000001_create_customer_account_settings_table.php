<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_account_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preferred_payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->unsignedTinyInteger('cutoff_day')->default(1);
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->boolean('is_statement_enabled')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'cutoff_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_settings');
    }
};
