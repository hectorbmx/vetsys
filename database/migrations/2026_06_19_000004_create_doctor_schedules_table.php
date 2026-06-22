<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'doctor_user_id', 'weekday', 'is_active'], 'doctor_schedules_lookup_index');
            $table->unique(
                ['tenant_id', 'doctor_user_id', 'weekday', 'starts_at', 'ends_at'],
                'doctor_schedules_unique_block'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};
