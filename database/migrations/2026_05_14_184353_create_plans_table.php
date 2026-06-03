<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('MXN');

            $table->enum('billing_period', [
                'monthly',
                'yearly',
                'one_time',
                'free',
            ])->default('monthly');

            $table->integer('max_users')->nullable();
            $table->integer('max_clients')->nullable();
            $table->integer('trial_days')->default(0);

            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};