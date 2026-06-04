<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_with_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 96)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'animal_id']);
            $table->index(['shared_with_tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_shares');
    }
};
