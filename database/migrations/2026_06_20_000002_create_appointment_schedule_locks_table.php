<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_schedule_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('schedule_date');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'doctor_user_id', 'schedule_date'],
                'appointment_schedule_locks_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_schedule_locks');
    }
};
