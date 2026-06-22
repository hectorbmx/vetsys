<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 80);
            $table->string('idempotency_key', 120);
            $table->char('request_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->string('result_type')->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'user_id', 'operation', 'idempotency_key'],
                'appointment_idempotency_keys_unique'
            );
            $table->index(['status', 'updated_at'], 'appointment_idempotency_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_idempotency_keys');
    }
};
