<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Services\ChildCrmChatClient;
use App\Support\ChildCrmSyncException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pushes one outbound chat message to its company's child CRM. Kept as its
 * own tiny per-message job (rather than folded into the poll-triggered pull
 * sync) so a slow/unreachable child API never blocks the admin's "send"
 * request — the message is saved locally as `pending` immediately, and this
 * job resolves it to `delivered`/`failed` in the background.
 */
class SendChatMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly ChatMessage $message) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ChildCrmChatClient $client): void
    {
        $company = $this->message->company;

        if (! $company || ! $company->is_active) {
            $this->markFailed('The company is no longer active.');

            return;
        }

        // Any ChildCrmSyncException here (SSRF-rejected URL, connection
        // failure/timeout) is worth retrying via $tries/backoff above rather
        // than caught locally — a rejected URL will keep being rejected, but
        // that's rare enough not to special-case, and a transient network
        // blip is exactly what the retry exists for. failed() below records
        // the final outcome once retries are exhausted.
        $response = $client->sendMessage($company, [
            'message_id' => $this->message->uuid,
            'body' => $this->message->body,
            'sender_name' => $this->message->sender_name,
            'sender_type' => ChatMessage::SENDER_MASTER,
            'created_at' => $this->message->occurred_at->toIso8601String(),
        ]);

        if ($response->successful()) {
            $this->message->update([
                'status' => ChatMessage::STATUS_DELIVERED,
                'synced_at' => now(),
            ]);

            return;
        }

        $reason = is_array($response->json()) ? ($response->json()['error'] ?? null) : null;
        $reason ??= "The child CRM returned an unexpected response (code {$response->status()}).";

        // An auth/not-found rejection will never succeed on retry — fail it
        // now instead of burning two more attempts. Anything else (429, 5xx)
        // is worth retrying, so it's thrown instead.
        if (in_array($response->status(), [401, 403, 404], true)) {
            $this->markFailed($reason);

            return;
        }

        throw new ChildCrmSyncException($reason);
    }

    /**
     * Called once $tries is exhausted on a genuinely transient failure
     * (connection errors, 429/5xx) — records the outcome the same way an
     * immediately-permanent failure does via `markFailed()` above.
     */
    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    private function markFailed(string $reason): void
    {
        $this->message->update([
            'status' => ChatMessage::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }
}
