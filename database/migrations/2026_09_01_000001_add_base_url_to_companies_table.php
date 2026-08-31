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
            // The single URL an admin enters on creation (e.g.
            // https://partneryx.xyz/functions/v1/) from which every child-CRM
            // endpoint — including api_url itself — is derived. Nullable
            // since existing companies predate this column and were seeded
            // with their endpoints entered directly.
            $table->string('base_url')->nullable()->after('website');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('base_url');
        });
    }
};
