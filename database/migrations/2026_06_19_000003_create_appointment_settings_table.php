<?php

use App\Enums\AppointmentCancellationPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('timezone', 64)->default('America/Mexico_City');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(15);
            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->unsignedInteger('minimum_notice_minutes')->default(120);
            $table->unsignedSmallInteger('booking_window_days')->default(60);
            $table->unsignedInteger('customer_cancellation_notice_minutes')->default(1440);
            $table->unsignedSmallInteger('proposal_hold_hours')->default(24);
            $table->unsignedSmallInteger('reminder_hours_before')->default(24);
            $table->string('cancellation_policy', 40)->default(AppointmentCancellationPolicy::NoPenalty->value);
            $table->string('late_fee_type', 20)->nullable();
            $table->decimal('late_fee_value', 12, 2)->nullable();
            $table->string('late_fee_collection_method', 20)->nullable();
            $table->foreignId('late_fee_catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->boolean('is_customer_booking_enabled')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'doctor_user_id']);
            $table->index(['tenant_id', 'is_customer_booking_enabled'], 'appointment_settings_booking_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_settings');
    }
};
