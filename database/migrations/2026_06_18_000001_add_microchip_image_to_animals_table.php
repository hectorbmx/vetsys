<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->string('microchip_image_path')->nullable()->after('microchip');
            $table->uuid('microchip_print_token')->nullable()->unique()->after('microchip_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropUnique(['microchip_print_token']);
            $table->dropColumn(['microchip_image_path', 'microchip_print_token']);
        });
    }
};
