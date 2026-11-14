<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quick_service_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requested_name', 150);
            $table->decimal('requested_price', 12, 2);
            $table->boolean('created_item');
            $table->timestamps();
            $table->unique(['tenant_id', 'client_uuid'], 'quick_service_tenant_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_service_operations');
    }
};
