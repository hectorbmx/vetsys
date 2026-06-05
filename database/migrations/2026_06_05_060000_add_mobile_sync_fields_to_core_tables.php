<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'animals', 'notes', 'payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'client_uuid')) {
                    $table->uuid('client_uuid')->nullable()->after('tenant_id');
                    $table->unique(['tenant_id', 'client_uuid'], $tableName . '_tenant_client_uuid_unique');
                }

                if (!Schema::hasColumn($tableName, 'synced_from_mobile')) {
                    $table->boolean('synced_from_mobile')->default(false)->after('client_uuid');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['payments', 'notes', 'animals', 'customers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'client_uuid')) {
                    $table->dropUnique($tableName . '_tenant_client_uuid_unique');
                }

                if (Schema::hasColumn($tableName, 'synced_from_mobile')) {
                    $table->dropColumn('synced_from_mobile');
                }

                if (Schema::hasColumn($tableName, 'client_uuid')) {
                    $table->dropColumn('client_uuid');
                }
            });
        }
    }
};
