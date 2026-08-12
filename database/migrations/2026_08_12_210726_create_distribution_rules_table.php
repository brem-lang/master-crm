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
        Schema::create('distribution_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('affiliate_id')->nullable();
            $table->string('advertiser_id')->nullable();
            $table->string('country_code')->nullable();
            $table->unsignedInteger('weight')->nullable();
            $table->unsignedBigInteger('daily_cap')->nullable();
            $table->unsignedBigInteger('hourly_cap')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('priority_type')->nullable();
            $table->unsignedInteger('priority')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->json('weekly_schedule')->nullable();
            $table->string('timezone')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_rules');
    }
};
