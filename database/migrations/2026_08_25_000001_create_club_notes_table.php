<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('folio');
            $table->string('public_token')->unique();
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['PENDIENTE', 'PAGADA', 'CANCELADA'])->default('PENDIENTE');
            $table->date('date_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'folio']);
            $table->index(['tenant_id', 'club_id', 'date_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_notes');
    }
};
