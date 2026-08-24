<?php

namespace App\Services;

use App\Models\Company;
use App\Services\Concerns\MakesChildCrmRequests;
use App\Support\ChildCrmSyncException;
use Illuminate\Http\Client\Response;

class ChildCrmChatClient
{
    use MakesChildCrmRequests;

    /**
     * Fetch one page of chat messages from a company's child CRM — same
     * page/limit/since/cursor shape as `ChildCrmLeadsClient::fetchPage()`,
     * so `CompanyChatSyncer` can reuse the same pagination loop.
     *
     * @return array{success?: bool, page?: int, pages?: int, next_cursor?: ?string, next_since?: ?string, data: list<array<string, mixed>>}
     *
     * @throws ChildCrmSyncException
     */
    public function fetchPage(Company $company, int $page, int $limit = 100, ?string $since = null): array
    {
        if (blank($company->get_chat_messages_url)) {
            throw new ChildCrmSyncException("No chat-messages API URL is configured for {$company->name}.");
        }

        $query = array_filter(
            ['page' => $page, 'limit' => $limit, 'since' => $since],
            fn ($value) => $value !== null,
        );

        $response = $this->request($company, $company->get_chat_messages_url, $query);
        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('data', $body)) {
            throw new ChildCrmSyncException("{$company->name}'s chat-messages API returned an unreadable response.");
        }

        return $body;
    }

    /**
     * Push one outbound message to the child CRM. Unlike `fetchPage()`, this
     * returns the raw response instead of throwing on a non-2xx status —
     * callers need the child's own error body to decide whether to retry.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ChildCrmSyncException
     */
    public function sendMessage(Company $company, array $payload): Response
    {
        if (blank($company->send_chat_message_url)) {
            throw new ChildCrmSyncException("No send-chat-message API URL is configured for {$company->name}.");
        }

        return $this->postJson($company, $company->send_chat_message_url, $payload);
    }
}
