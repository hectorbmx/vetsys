<?php

use App\Support\TenantThemePalettes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('theme_palette', 32)
                ->default(TenantThemePalettes::DEFAULT)
                ->after('onboarding_banner_dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('theme_palette');
        });
    }
};
