<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('study_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'animal_id']);
            $table->index(['tenant_id', 'study_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_studies');
    }
};
