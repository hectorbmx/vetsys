<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('push_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32);
            $table->string('recipient_key', 191);
            $table->char('recipient_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['appointment_event_id', 'channel', 'recipient_hash'],
                'appointment_delivery_event_channel_recipient_unique'
            );
            $table->index(['tenant_id', 'status']);
            $table->index(['push_device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notification_deliveries');
    }
};
