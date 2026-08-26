<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_notes', function (Blueprint $table) {
            $table->longText('notes_html')->nullable()->after('date_at');
        });
    }

    public function down(): void
    {
        Schema::table('club_notes', function (Blueprint $table) {
            $table->dropColumn('notes_html');
        });
    }
};
