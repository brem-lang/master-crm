# Child CRM Chat API

This document specifies the two endpoints a child CRM must implement so its
company can use the Master CRM's chat feature. It mirrors the existing
leads/affiliates/advertisers/distribution-rules integration already in place
— same authentication, same pagination shape — so if your CRM already
implements one of those, this should look familiar.

Master CRM pulls new messages from you on a short poll (a few seconds, while
an admin has the chat window open) and pushes messages it sends to you via a
direct POST. There is no websocket/webhook requirement on either side.

## Authentication

Every request from Master CRM carries the company's API key in **both**
headers (send whichever your framework reads more easily — both are always
present and always equal):

```
Api-Key: <company's api key>
Authorization: Bearer <company's api key>
```

Reject the request with `401` or `403` if the key doesn't match what you have
on file for that company.

## 1. `GET {your-endpoint}` — list new chat messages

Called on a poll to fetch messages your side has that Master CRM doesn't have
yet.

### Query parameters

| Param   | Type   | Required | Description                                                                                                                                                     |
| ------- | ------ | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `page`  | int    | yes      | Zero-indexed page number. Master CRM always starts at `page=0`.                                                                                                 |
| `limit` | int    | yes      | Max rows to return in this page (Master CRM sends `100`).                                                                                                       |
| `since` | string | no       | ISO 8601 timestamp. When present, only return messages with `created_at >= since` (inclusive is fine — see note below). Omitted on a company's very first sync. |

### Response `200`

```json
{
    "success": true,
    "page": 0,
    "pages": 1,
    "next_cursor": "msg-482",
    "next_since": "2026-08-25T10:03:00Z",
    "data": [
        {
            "id": "msg-481",
            "body": "We have a question about lead routing.",
            "sender_name": "Jane Doe",
            "created_at": "2026-08-25T10:00:00Z"
        },
        {
            "id": "msg-482",
            "body": "Can you resend lead #38210?",
            "sender_name": "Jane Doe",
            "created_at": "2026-08-25T10:03:00Z"
        }
    ]
}
```

| Field                | Type              | Required | Notes                                                                                                                                                    |
| -------------------- | ----------------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `data`               | array             | **yes**  | The only field Master CRM strictly requires — everything else is optional but strongly recommended.                                                      |
| `data[].id`          | string            | yes      | Your own message id. Used as the idempotency key — Master CRM will never store the same `id` twice.                                                      |
| `data[].body`        | string            | yes      | The message text. A row missing `id` or `body` is silently skipped, not an error.                                                                        |
| `data[].sender_name` | string \| null    | no       | Shown in the Master CRM UI. Omit or send `null` if you don't track a display name.                                                                       |
| `data[].created_at`  | string (ISO 8601) | no       | When the message was actually sent on your side. Defaults to "now" (master-side) if omitted — sending it is recommended so the timeline is accurate.     |
| `page`, `pages`      | int               | no       | Only `pages` is consulted — if it's greater than 1, Master CRM requests the next page(s) automatically.                                                  |
| `next_cursor`        | string \| null    | no       | Echoed back to you as `since`... actually see note below — currently unused by Master CRM, reserved for future cursor-based paging. Safe to omit.        |
| `next_since`         | string \| null    | no       | **Recommended.** The timestamp Master CRM should send back as `since` on its _next_ sync. If omitted, Master CRM keeps using the `since` it already had. |
| `data: []`           | —                 | —        | An empty array means "nothing new" — Master CRM stops paging as soon as it sees this.                                                                    |

**Ordering:** return messages in ascending `created_at`/`id` order.

**The `since` filter should be inclusive.** Master CRM's own sync logic
tolerates re-seeing the same boundary message on the next poll (it dedupes by
`id`), so when in doubt, err on the side of returning too much rather than
accidentally skipping a message with an exact-timestamp collision.

**Timeouts:** Master CRM allows 2s to connect and 5s total per request, and
does not follow redirects. Respond within that window.

## 2. `POST {your-endpoint}` — receive a message from Master CRM

Called once per outgoing message — a parent-admin or child-admin sent
something from the Master CRM side and it's being pushed to you.

### Request body

```json
{
    "message_id": "8f14e45f-ceea-467e-bd3f-4a1c8a9e2b4d",
    "body": "Thanks, I'll look into that lead now.",
    "sender_name": "Alex Kim",
    "sender_type": "master",
    "created_at": "2026-08-25T10:05:00Z"
}
```

| Field         | Type   | Description                                                                                |
| ------------- | ------ | ------------------------------------------------------------------------------------------ |
| `message_id`  | string | A UUID generated by Master CRM. **Use this as your idempotency key** — see retries, below. |
| `body`        | string | The message text (max 4000 characters).                                                    |
| `sender_name` | string | Display name of the admin who sent it.                                                     |
| `sender_type` | string | Always the literal string `"master"`.                                                      |
| `created_at`  | string | ISO 8601 timestamp of when the message was sent.                                           |

### Response

**Success — any `2xx`:**

```json
{ "status": "received", "id": "your-internal-id" }
```

The body is read but not required beyond a `2xx` status; returning your own
`id` is optional but helps if you ever need to cross-reference logs.

**Failure — any non-`2xx`:**

```json
{ "error": "Human-readable reason" }
```

Return `error` as a plain string — it's relayed as-is to the admin who sent
the message, so keep it short and non-technical.

### Retries — please dedupe by `message_id`

Master CRM retries a failed send automatically:

- `401`/`403`/`404` are treated as permanent — **not retried.**
- Anything else (connection failure, `429`, `5xx`) is retried up to 3
  attempts total, with backoff of 30s, then 2 minutes, then 5 minutes.

Because of this, you may receive the same `message_id` more than once (e.g. if
you returned `200` but the response was lost in transit). Storing on
`message_id` with an upsert/unique-constraint, rather than a plain insert,
makes this safe.

### Timeouts

Same as the GET endpoint: 2s to connect, 5s total, no redirects followed.

## Company setup checklist

Send whoever configures the company in Master CRM these two full URLs (they
follow the same `{api_url}/{slug}` convention as your other endpoints):

- `get-chat-messages` → the `GET` endpoint above
- `send-chat-message` → the `POST` endpoint above

Nothing else is required — there's no separate registration/handshake step,
and the chat thread is scoped to the company automatically by whichever API
key you're called with.
