<?php

use App\Enums\AppointmentCancellationFeeStatus;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->string('service_name_snapshot');
            $table->string('animal_name_snapshot');
            $table->string('doctor_name_snapshot');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->string('status', 40)->default(AppointmentStatus::PendingTenant->value);
            $table->text('customer_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('is_late_cancellation')->default(false);
            $table->string('cancellation_fee_status', 40)
                ->default(AppointmentCancellationFeeStatus::NotApplicable->value);
            $table->decimal('cancellation_fee_amount', 12, 2)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'starts_at'], 'appointments_status_start_index');
            $table->index(['tenant_id', 'doctor_user_id', 'starts_at'], 'appointments_doctor_start_index');
            $table->index(['tenant_id', 'customer_id', 'starts_at'], 'appointments_customer_start_index');
            $table->index(['tenant_id', 'animal_id', 'starts_at'], 'appointments_animal_start_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
