<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
        {
            Schema::table('tenant_billing_profiles', function (Blueprint $table) {
                $table->string('csd_cer_path')->nullable()->after('email');
                $table->string('csd_key_path')->nullable()->after('csd_cer_path');
                $table->text('csd_password')->nullable()->after('csd_key_path');
            });
        }

        public function down(): void
        {
            Schema::table('tenant_billing_profiles', function (Blueprint $table) {
                $table->dropColumn([
                    'csd_cer_path',
                    'csd_key_path',
                    'csd_password',
                ]);
            });
        }
};
