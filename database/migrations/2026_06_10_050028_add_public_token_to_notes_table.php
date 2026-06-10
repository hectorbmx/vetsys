<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('folio');
        });

        // Populate existing notes with a token
        DB::table('notes')->whereNull('public_token')->get()->each(function ($note) {
            DB::table('notes')
                ->where('id', $note->id)
                ->update(['public_token' => Str::random(32) . $note->id]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
