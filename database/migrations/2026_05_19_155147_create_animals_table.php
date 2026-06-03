<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('animal_type_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('photo_path')->nullable();

            $table->enum('sex', [
                'male',
                'female',
                'unknown',
            ])->default('unknown');

            $table->date('birthdate')->nullable();

            $table->string('color')->nullable();

            $table->decimal('weight', 10, 2)->nullable();

            $table->string('microchip')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
                'deceased',
                'transferred',
            ])->default('active');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'tenant_id',
                'customer_id',
            ]);

            $table->index([
                'tenant_id',
                'animal_type_id',
            ]);

            $table->index([
                'tenant_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'name',
            ]);

            $table->index([
                'tenant_id',
                'microchip',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};