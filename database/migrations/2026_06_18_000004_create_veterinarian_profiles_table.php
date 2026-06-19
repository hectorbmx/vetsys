<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinarian_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('professional_name');
            $table->string('professional_title', 100);
            $table->string('license_number', 100);
            $table->string('specialty')->nullable();
            $table->string('professional_phone', 50)->nullable();
            $table->string('professional_email')->nullable();
            $table->text('professional_address')->nullable();
            $table->string('signature_disk')->nullable();
            $table->string('signature_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'license_number']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterinarian_profiles');
    }
};
