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
        Schema::table('animal_reports', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('author_id');
        });

        DB::table('animal_reports')->whereNull('public_token')->get()->each(function ($report) {
            DB::table('animal_reports')
                ->where('id', $report->id)
                ->update(['public_token' => Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::table('animal_reports', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
