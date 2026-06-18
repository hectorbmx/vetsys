<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->date('report_date');
            $table->longText('content_html');
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->string('pdf_disk')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'animal_id', 'report_date']);
        });

        Schema::create('animal_report_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_report_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('r2');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->default('image/webp');
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'animal_report_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_report_images');
        Schema::dropIfExists('animal_reports');
    }
};
