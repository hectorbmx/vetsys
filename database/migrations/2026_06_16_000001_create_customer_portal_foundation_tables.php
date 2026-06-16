<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_portal_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_portal_enabled')->default(true);
            $table->boolean('is_mobile_access_enabled')->default(true);
            $table->enum('access_mode', ['free', 'paid', 'included', 'disabled'])->default('free');
            $table->enum('default_access_status', ['active', 'invited', 'disabled'])->default('active');
            $table->boolean('requires_manual_activation')->default(true);
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('MXN');
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index(['tenant_id', 'access_mode']);
        });

        Schema::create('customer_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('relationship', ['owner', 'guardian', 'payer', 'viewer'])->default('owner');
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id', 'user_id'], 'customer_user_links_unique');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'revoked_at']);
        });

        Schema::create('customer_portal_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['invited', 'active', 'suspended', 'expired', 'revoked'])->default('active');
            $table->enum('billing_mode', ['free', 'included', 'paid', 'trial'])->default('free');
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('access_starts_at')->nullable();
            $table->timestamp('access_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('last_paid_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id', 'user_id'], 'customer_portal_accesses_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'access_ends_at']);
        });

        Schema::create('final_user_patient_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'animal_id'], 'final_user_patient_assignments_unique');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'animal_id']);
            $table->index(['tenant_id', 'revoked_at']);
        });

        Schema::create('animal_portal_visibility_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->boolean('show_profile')->default(false);
            $table->boolean('show_history')->default(false);
            $table->boolean('show_notes')->default(false);
            $table->boolean('show_services')->default(false);
            $table->boolean('show_products')->default(false);
            $table->boolean('show_files')->default(false);
            $table->boolean('show_videos')->default(false);
            $table->boolean('show_radiology')->default(false);
            $table->boolean('show_statement')->default(false);
            $table->boolean('show_vaccines')->default(false);
            $table->boolean('show_appointments')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'animal_id'], 'animal_portal_visibility_unique');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'animal_id']);
        });

        Schema::create('portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->string('title');
            $table->text('body')->nullable();
            $table->text('url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'user_id', 'read_at']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'animal_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_notifications');
        Schema::dropIfExists('animal_portal_visibility_settings');
        Schema::dropIfExists('final_user_patient_assignments');
        Schema::dropIfExists('customer_portal_accesses');
        Schema::dropIfExists('customer_user_links');
        Schema::dropIfExists('tenant_portal_settings');
    }
};
