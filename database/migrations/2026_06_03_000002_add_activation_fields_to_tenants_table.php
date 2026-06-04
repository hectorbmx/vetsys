<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('activation_code_token')->nullable()->after('created_by');
            $table->string('activation_link_token')->nullable()->after('activation_code_token');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_link_token');
            $table->timestamp('activated_at')->nullable()->after('activation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'activation_code_token',
                'activation_link_token',
                'activation_expires_at',
                'activated_at',
            ]);
        });
    }
};
