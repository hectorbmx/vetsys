<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->string('evidence_disk')->nullable()->after('payment_reference');
            $table->string('evidence_path')->nullable()->after('evidence_disk');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropColumn(['evidence_disk', 'evidence_path']);
        });
    }
};
