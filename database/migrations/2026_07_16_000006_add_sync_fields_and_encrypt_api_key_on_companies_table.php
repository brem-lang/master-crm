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
            $table->timestamp('last_synced_at')->nullable()->after('is_active');
            $table->timestamp('last_synced_since')->nullable()->after('last_synced_at');
            $table->string('last_synced_cursor')->nullable()->after('last_synced_since');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('api_key', 500)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['last_synced_at', 'last_synced_since', 'last_synced_cursor']);
            $table->string('api_key')->change();
        });
    }
};
