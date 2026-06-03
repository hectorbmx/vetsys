<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_type_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('animal_type_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('label');
            $table->string('slug');

            $table->enum('field_type', [
                'text',
                'textarea',
                'number',
                'decimal',
                'date',
                'datetime',
                'select',
                'multiselect',
                'checkbox',
                'boolean',
                'file',
                'image',
            ]);

            $table->json('options_json')->nullable();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->string('help_text')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'animal_type_id',
                'slug',
            ]);

            $table->index([
                'tenant_id',
                'animal_type_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_type_fields');
    }
};