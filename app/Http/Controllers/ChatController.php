<?php

namespace App\Http\Controllers;

use App\Jobs\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Services\CompanyChatSyncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One chat thread per company between its own child-admin staff and master
 * (parent-admin) users. Access mirrors CompanyHealthController's self-service
 * check: a parent-admin (`manage-companies`) reaches any company's thread, a
 * child-admin (`manage-own-company`) only their own.
 */
class ChatController extends Controller
{
    /**
     * How often a poll request is allowed to trigger a live pull from the
     * child CRM, per company — keeps a chat window left open from hammering
     * a slow child API on every request.
     */
    private const SYNC_THROTTLE_SECONDS = 4;

    public function index(Company $company): Response
    {
        $this->authorizeAccess($company);

        return Inertia::render('chat/index', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'messages' => $this->recentMessages($company),
        ]);
    }

    public function messages(Request $request, Company $company, CompanyChatSyncer $syncer): JsonResponse
    {
        $this->authorizeAccess($company);

        // A cache lock (rather than a plain timestamp check) so two browser
        // tabs polling the same company at once can't both slip through and
        // double up the outbound request to the child CRM.
        Cache::lock("chat-sync-company-{$company->id}", self::SYNC_THROTTLE_SECONDS)
            ->get(fn () => $syncer->sync($company));

        $afterId = $request->integer('after_id') ?: null;

        return response()->json([
            'data' => $this->recentMessages($company, $afterId),
        ]);
    }

    public function store(Request $request, Company $company): JsonResponse
    {
        $this->authorizeAccess($company);

        abort_unless($company->is_active, 422, 'Cannot message an inactive company.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $user = Auth::user();

        $message = ChatMessage::create([
            'company_id' => $company->id,
            'uuid' => (string) Str::uuid(),
            'direction' => ChatMessage::DIRECTION_OUTBOUND,
            'sender_type' => ChatMessage::SENDER_MASTER,
            'sender_user_id' => $user->id,
            'sender_name' => $user->name,
            'body' => $validated['body'],
            'status' => ChatMessage::STATUS_PENDING,
            'occurred_at' => now(),
        ]);

        SendChatMessageJob::dispatch($message);

        return response()->json(['data' => $this->formatMessage($message)], 201);
    }

    private function authorizeAccess(Company $company): void
    {
        $user = Auth::user();
        $ownsCompany = $user->company_id && $user->company_id === $company->id;

        abort_unless($user->can('manage-companies') || ($ownsCompany && $user->can('manage-own-company')), 403);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentMessages(Company $company, ?int $afterId = null): array
    {
        return ChatMessage::where('company_id', $company->id)
            ->when($afterId, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map($this->formatMessage(...))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'sender_type' => $message->sender_type,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender_name,
            'body' => $message->body,
            'status' => $message->status,
            'failure_reason' => $message->failure_reason,
            'occurred_at' => $message->occurred_at->toIso8601String(),
        ];
    }
}
