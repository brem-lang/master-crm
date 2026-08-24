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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            // The child CRM's own id for an inbound message — null for outbound
            // ones (the master-generated `uuid` above is what's sent to the
            // child as *its* idempotency key instead).
            $table->string('external_id')->nullable();
            $table->string('direction');
            $table->string('sender_type');
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->text('body');
            // pending|sent|delivered|failed — meaningful for outbound messages
            // only; inbound rows are always created already 'delivered'.
            $table->string('status')->default('delivered');
            $table->text('failure_reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_id']);
            $table->index(['company_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
