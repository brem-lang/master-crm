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
            $table->string('affiliate_count_api_url')->nullable()->after('affiliates_url');
            $table->string('advertiser_count_api_url')->nullable()->after('advertisers_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['affiliate_count_api_url', 'advertiser_count_api_url']);
        });
    }
};
