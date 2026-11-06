<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->foreignId('replaces_tenant_payment_id')->nullable()->unique()
                ->constrained('tenant_payments')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropUnique('tenant_payments_replaces_tenant_payment_id_unique');
            $table->dropConstrainedForeignId('replaces_tenant_payment_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
