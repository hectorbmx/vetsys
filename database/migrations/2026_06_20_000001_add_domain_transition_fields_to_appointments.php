<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('rejected_at')->nullable()->after('confirmed_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->dateTime('no_show_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'rejection_reason', 'no_show_at']);
        });
    }
};
