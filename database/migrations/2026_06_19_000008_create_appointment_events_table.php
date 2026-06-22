<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('previous_status', 40)->nullable();
            $table->string('new_status', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'appointment_id', 'created_at'], 'appointment_events_timeline_index');
            $table->index(['tenant_id', 'event_type', 'created_at'], 'appointment_events_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_events');
    }
};
