<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->boolean('is_bookable')->default(false)->after('is_active');
            $table->unsignedSmallInteger('appointment_duration_minutes')->nullable()->after('is_bookable');
            $table->unsignedSmallInteger('appointment_buffer_minutes')->default(0)->after('appointment_duration_minutes');
            $table->text('booking_description')->nullable()->after('appointment_buffer_minutes');

            $table->index(['tenant_id', 'type', 'is_active', 'is_bookable'], 'catalog_items_bookable_index');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropIndex('catalog_items_bookable_index');
            $table->dropColumn([
                'is_bookable',
                'appointment_duration_minutes',
                'appointment_buffer_minutes',
                'booking_description',
            ]);
        });
    }
};
