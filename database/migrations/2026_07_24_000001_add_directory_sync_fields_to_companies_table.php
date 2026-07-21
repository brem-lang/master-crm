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
            $table->string('affiliates_url')->nullable()->after('leads_count_url');
            $table->string('advertisers_url')->nullable()->after('affiliates_url');

            $table->timestamp('affiliates_last_synced_at')->nullable()->after('last_synced_cursor');
            $table->timestamp('affiliates_last_synced_since')->nullable()->after('affiliates_last_synced_at');
            $table->string('affiliates_last_synced_cursor')->nullable()->after('affiliates_last_synced_since');

            $table->timestamp('advertisers_last_synced_at')->nullable()->after('affiliates_last_synced_cursor');
            $table->timestamp('advertisers_last_synced_since')->nullable()->after('advertisers_last_synced_at');
            $table->string('advertisers_last_synced_cursor')->nullable()->after('advertisers_last_synced_since');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'affiliates_url',
                'advertisers_url',
                'affiliates_last_synced_at',
                'affiliates_last_synced_since',
                'affiliates_last_synced_cursor',
                'advertisers_last_synced_at',
                'advertisers_last_synced_since',
                'advertisers_last_synced_cursor',
            ]);
        });
    }
};
