<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->string('device_uuid', 191);
            $table->string('device_name', 100)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'device_uuid'], 'push_devices_user_platform_device_unique');
            $table->index(['tenant_id', 'revoked_at']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
