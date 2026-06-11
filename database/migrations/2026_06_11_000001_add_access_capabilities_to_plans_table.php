<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('web_access')->default(true)->after('max_clients');
            $table->boolean('mobile_access')->default(false)->after('web_access');
            $table->unsignedInteger('max_web_sessions_per_user')->default(1)->after('mobile_access');
            $table->unsignedInteger('max_mobile_sessions_per_user')->default(0)->after('max_web_sessions_per_user');
            $table->boolean('allow_cross_platform_sessions')->default(false)->after('max_mobile_sessions_per_user');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'web_access',
                'mobile_access',
                'max_web_sessions_per_user',
                'max_mobile_sessions_per_user',
                'allow_cross_platform_sessions',
            ]);
        });
    }
};
