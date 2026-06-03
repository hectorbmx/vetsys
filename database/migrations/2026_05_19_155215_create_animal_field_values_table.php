<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_field_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('animal_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('animal_type_field_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('value_text')->nullable();
            $table->bigInteger('value_number')->nullable();
            $table->decimal('value_decimal', 15, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();
            $table->string('file_path')->nullable();

            $table->timestamps();

            $table->unique([
                'animal_id',
                'animal_type_field_id',
            ], 'animal_field_unique');

            $table->index([
                'tenant_id',
                'animal_id',
            ]);

            $table->index([
                'tenant_id',
                'animal_type_field_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_field_values');
    }
};