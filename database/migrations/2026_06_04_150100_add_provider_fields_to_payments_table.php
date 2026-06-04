<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->default('manual')->after('payment_method_id');
            $table->string('provider_payment_id')->nullable()->index()->after('provider');
            $table->string('provider_session_id')->nullable()->index()->after('provider_payment_id');
            $table->string('status')->default('paid')->index()->after('provider_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'provider_payment_id',
                'provider_session_id',
                'status',
            ]);
        });
    }
};
