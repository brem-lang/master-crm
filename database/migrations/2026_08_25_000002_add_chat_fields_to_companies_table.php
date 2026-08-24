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
            $table->string('get_chat_messages_url')->nullable()->after('distribution_rules_url');
            $table->string('send_chat_message_url')->nullable()->after('get_chat_messages_url');
            $table->timestamp('chat_last_synced_at')->nullable()->after('send_chat_message_url');
            $table->timestamp('chat_last_synced_since')->nullable()->after('chat_last_synced_at');
            $table->string('chat_last_synced_cursor')->nullable()->after('chat_last_synced_since');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'get_chat_messages_url',
                'send_chat_message_url',
                'chat_last_synced_at',
                'chat_last_synced_since',
                'chat_last_synced_cursor',
            ]);
        });
    }
};
