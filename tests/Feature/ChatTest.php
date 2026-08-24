<?php

use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a parent admin can view any company\'s chat thread', function () {
    $company = Company::factory()->create();
    ChatMessage::factory()->create(['company_id' => $company->id, 'body' => 'Hello there']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('chat.index', $company));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];
    expect($props['company']['id'])->toBe($company->id);
    expect($props['messages'])->toHaveCount(1);
});

test('a child admin can view their own company\'s chat thread', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('chat.index', $company));

    $response->assertOk();
});

test('a child admin cannot view another company\'s chat thread', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('chat.index', $otherCompany));

    $response->assertForbidden();
});

test('a sales rep cannot view a chat thread', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('chat.index', $company));

    $response->assertForbidden();
});

test('sending a message stores it as pending and delivers it via the queued job', function () {
    Http::fake([
        'https://example.com/send-chat-message' => Http::response(['status' => 'received', 'id' => 'child-1'], 200),
    ]);

    $company = Company::factory()->create(['send_chat_message_url' => 'https://example.com/send-chat-message']);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->postJson(route('chat.store', $company), ['body' => 'Hi, need help with a lead.']);

    $response->assertCreated();
    expect($response->json('data.body'))->toBe('Hi, need help with a lead.');

    $message = ChatMessage::where('company_id', $company->id)->first();
    expect($message->direction)->toBe(ChatMessage::DIRECTION_OUTBOUND);
    expect($message->sender_user_id)->toBe($childAdmin->id);
    // QUEUE_CONNECTION=sync in tests, so the dispatched SendChatMessageJob has
    // already run by the time the response comes back.
    expect($message->status)->toBe(ChatMessage::STATUS_DELIVERED);

    Http::assertSent(fn ($request) => $request['message_id'] === $message->uuid && $request->hasHeader('Api-Key', $company->api_key));
});

test('a child CRM rejecting a message with 401 marks it failed without retrying', function () {
    Http::fake([
        'https://example.com/send-chat-message' => Http::response(['error' => 'Invalid API key'], 401),
    ]);

    $company = Company::factory()->create(['send_chat_message_url' => 'https://example.com/send-chat-message']);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $this->actingAs($childAdmin)->postJson(route('chat.store', $company), ['body' => 'Ping']);

    $message = ChatMessage::where('company_id', $company->id)->first();
    expect($message->status)->toBe(ChatMessage::STATUS_FAILED);
    expect($message->failure_reason)->toBe('Invalid API key');
    Http::assertSentCount(1);
});

test('sending a message to an inactive company is rejected', function () {
    $company = Company::factory()->create(['is_active' => false]);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->postJson(route('chat.store', $company), ['body' => 'Hello']);

    $response->assertStatus(422);
    expect(ChatMessage::where('company_id', $company->id)->exists())->toBeFalse();
});

test('polling for messages pulls new inbound messages from the child CRM', function () {
    Http::fake([
        'https://example.com/get-chat-messages*' => Http::response([
            'success' => true,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'msg-1',
            'next_since' => '2026-08-25T10:00:00Z',
            'data' => [[
                'id' => 'msg-1',
                'body' => 'We have a question about lead routing.',
                'sender_name' => 'Jane at Acme',
                'created_at' => '2026-08-25T10:00:00Z',
            ]],
        ]),
    ]);

    $company = Company::factory()->create(['get_chat_messages_url' => 'https://example.com/get-chat-messages']);
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->getJson(route('chat.messages', $company));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.body'))->toBe('We have a question about lead routing.');
    expect($response->json('data.0.sender_type'))->toBe(ChatMessage::SENDER_CHILD);

    $company->refresh();
    expect($company->chat_last_synced_cursor)->toBe('msg-1');
});

test('polling only returns messages after the given id', function () {
    $company = Company::factory()->create();
    $first = ChatMessage::factory()->create(['company_id' => $company->id, 'body' => 'first']);
    $second = ChatMessage::factory()->create(['company_id' => $company->id, 'body' => 'second']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->getJson(route('chat.messages', $company).'?after_id='.$first->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($second->id);
});
