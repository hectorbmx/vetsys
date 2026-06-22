<?php

use App\Enums\AppointmentProposalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('previous_appointment_status', 40);
            $table->text('message')->nullable();
            $table->string('status', 40)->default(AppointmentProposalStatus::Pending->value);
            $table->dateTime('expires_at');
            $table->dateTime('responded_at')->nullable();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'appointment_id', 'status'], 'appointment_proposals_status_index');
            $table->index(['tenant_id', 'status', 'expires_at'], 'appointment_proposals_expiry_index');
            $table->index(['tenant_id', 'starts_at', 'ends_at'], 'appointment_proposals_slot_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_proposals');
    }
};
