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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('request_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('country_code')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('status')->nullable();
            $table->string('affiliate_name')->nullable();
            $table->boolean('is_ftd')->default(false);
            $table->string('offer_name')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('lead_created_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('synced_to_parent_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
