<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'notes',
            'animal_videos',
            'radiology_studies',
            'radiology_images',
            'vaccination_letters',
            'customer_statements',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('visible_to_customer')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

                $table->index(['tenant_id', 'visible_to_customer']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'customer_statements',
            'vaccination_letters',
            'radiology_images',
            'radiology_studies',
            'animal_videos',
            'notes',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign($tableName . '_published_by_foreign');
                $table->dropIndex($tableName . '_tenant_id_visible_to_customer_index');
                $table->dropColumn([
                    'visible_to_customer',
                    'published_at',
                    'published_by',
                ]);
            });
        }
    }
};
