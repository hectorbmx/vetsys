<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('r2');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->date('video_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'animal_id']);
            $table->index(['tenant_id', 'video_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_videos');
    }
};
