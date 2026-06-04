<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->date('date');
            $table->timestamps();

            $table->index(['tenant_id', 'animal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_letters');
    }
};
