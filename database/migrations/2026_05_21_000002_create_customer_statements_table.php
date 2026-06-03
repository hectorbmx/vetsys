<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedTinyInteger('cutoff_day');
            $table->decimal('previous_balance', 12, 2)->default(0);
            $table->decimal('period_charges', 12, 2)->default(0);
            $table->decimal('period_payments', 12, 2)->default(0);
            $table->decimal('ending_balance', 12, 2)->default(0);
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->enum('status', ['generated', 'void', 'regenerated'])->default('generated');
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id', 'period_start', 'period_end'], 'customer_statements_period_unique');
            $table->index(['tenant_id', 'period_end']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_statements');
    }
};
