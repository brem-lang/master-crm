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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('distribution_rules_url')->nullable()->after('update_affiliate_status_url');

            $table->timestamp('distribution_rules_last_synced_at')->nullable()->after('advertisers_last_synced_cursor');
            $table->timestamp('distribution_rules_last_synced_since')->nullable()->after('distribution_rules_last_synced_at');
            $table->string('distribution_rules_last_synced_cursor')->nullable()->after('distribution_rules_last_synced_since');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'distribution_rules_url',
                'distribution_rules_last_synced_at',
                'distribution_rules_last_synced_since',
                'distribution_rules_last_synced_cursor',
            ]);
        });
    }
};
