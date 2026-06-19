<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vaccination_letters', 'public_token')) {
            Schema::table('vaccination_letters', function (Blueprint $table) {
                $table->string('public_token', 64)->nullable()->unique()->after('animal_id');
                $table->string('pdf_disk')->nullable()->after('image_path');
                $table->string('pdf_path')->nullable()->after('pdf_disk');
                $table->timestamp('finalized_at')->nullable()->after('pdf_path');
            });
        }

        DB::table('vaccination_letters')->whereNull('public_token')->get()->each(function ($letter) {
            DB::table('vaccination_letters')
                ->where('id', $letter->id)
                ->update(['public_token' => Str::random(48)]);
        });

        if (! Schema::hasColumn('animals', 'microchip_issued_by')) {
            Schema::table('animals', function (Blueprint $table) {
                // Kept as an indexed reference because legacy zero dates prevent MySQL table rebuilds for FK creation.
                $table->unsignedBigInteger('microchip_issued_by')->nullable()->after('microchip_print_token')->index();
                $table->string('microchip_pdf_disk')->nullable()->after('microchip_issued_by');
                $table->string('microchip_pdf_path')->nullable()->after('microchip_pdf_disk');
                $table->timestamp('microchip_finalized_at')->nullable()->after('microchip_pdf_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropIndex(['microchip_issued_by']);
            $table->dropColumn(['microchip_issued_by', 'microchip_pdf_disk', 'microchip_pdf_path', 'microchip_finalized_at']);
        });

        Schema::table('vaccination_letters', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['public_token', 'pdf_disk', 'pdf_path', 'finalized_at']);
        });
    }
};
