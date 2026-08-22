<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radiology_studies', function (Blueprint $table) {
            $table->string('modality', 20)->default('radiology')->after('animal_id');
            $table->index(['tenant_id', 'animal_id', 'modality'], 'radiology_studies_tenant_animal_modality_index');
        });
    }

    public function down(): void
    {
        Schema::table('radiology_studies', function (Blueprint $table) {
            $table->dropIndex('radiology_studies_tenant_animal_modality_index');
            $table->dropColumn('modality');
        });
    }
};
