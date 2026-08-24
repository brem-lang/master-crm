<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Company;
use App\Services\Concerns\BulkUpsertsExternalRecords;
use App\Support\ChildCrmSyncException;
use Illuminate\Support\Str;

/**
 * Pulls new inbound chat messages from a company's child CRM — same
 * page/since/cursor pagination shape as `CompanyLeadsSyncer`, kept as its own
 * class (rather than folded into `CompanyDirectorySyncer`) because chat
 * messages are never soft-deleted and never edited, so the sync loop is
 * simpler: no `deleted_at` handling, no in-place updates, append-only.
 */
class CompanyChatSyncer
{
    use BulkUpsertsExternalRecords;

    private const LIMIT = 100;

    /**
     * Hard cap on pages processed per sync, guarding against a child API that
     * misreports an absurd page count. Lower than the leads/directory syncers'
     * 500 since a single poll-triggered sync should stay fast.
     */
    private const MAX_PAGES = 50;

    public function __construct(private readonly ChildCrmChatClient $client) {}

    /**
     * @return array{success: bool, pulled: int, message: string}
     */
    public function sync(Company $company): array
    {
        if (blank($company->get_chat_messages_url)) {
            return ['success' => true, 'pulled' => 0, 'message' => "No chat-messages API URL configured for {$company->name}."];
        }

        $since = $company->chat_last_synced_since?->toIso8601String();

        try {
            $first = $this->client->fetchPage($company, 0, self::LIMIT, $since);
        } catch (ChildCrmSyncException $e) {
            return ['success' => false, 'pulled' => 0, 'message' => $e->getMessage()];
        }

        $totalPages = $first['pages'] ?? 1;
        $pages = min($totalPages, self::MAX_PAGES);

        if ($totalPages > self::MAX_PAGES) {
            logger()->warning("{$company->name} has {$totalPages} chat-message pages, more than the ".self::MAX_PAGES.' per-run cap — this run only pulled the first '.self::MAX_PAGES.'; the rest will be picked up on a later poll.');
        }

        $pulled = 0;
        $skipped = 0;

        try {
            for ($page = 0; $page < $pages; $page++) {
                $response = $page === 0 ? $first : $this->client->fetchPage($company, $page, self::LIMIT, $since);

                $items = $response['data'];

                if ($items === []) {
                    break;
                }

                $rows = [];

                foreach ($items as $item) {
                    if (blank($item['id'] ?? null) || blank($item['body'] ?? null)) {
                        $skipped++;

                        continue;
                    }

                    $rows[(string) $item['id']] = $this->mapMessage($item);
                }

                $pulled += $this->bulkUpsert(ChatMessage::class, $company, $rows);

                // Persist the resume point after every page, not just once at the
                // end — if the request gets cut off mid-sync, the next poll
                // resumes from here instead of re-pulling from scratch.
                $company->update([
                    'chat_last_synced_since' => $response['next_since'] ?? $company->chat_last_synced_since,
                    'chat_last_synced_cursor' => $response['next_cursor'] ?? $company->chat_last_synced_cursor,
                ]);
            }
        } catch (ChildCrmSyncException $e) {
            return ['success' => false, 'pulled' => $pulled, 'message' => $e->getMessage()];
        }

        if ($skipped > 0) {
            logger()->warning("Skipped {$skipped} chat message(s) from {$company->name} with no id/body.");
        }

        $company->update(['chat_last_synced_at' => now()]);

        return [
            'success' => true,
            'pulled' => $pulled,
            'message' => $pulled > 0 ? "Pulled {$pulled} new message(s) from {$company->name}." : "No new messages from {$company->name}.",
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapMessage(array $item): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'sender_type' => ChatMessage::SENDER_CHILD,
            'sender_name' => $item['sender_name'] ?? null,
            'body' => $item['body'],
            'status' => ChatMessage::STATUS_DELIVERED,
            'occurred_at' => $item['created_at'] ?? now(),
            'synced_at' => now(),
        ];
    }
}
