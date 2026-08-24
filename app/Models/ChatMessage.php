<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message in a company's chat thread with the master CRM. `direction`
 * says which way it travelled — 'outbound' was authored here and pushed to
 * the child CRM, 'inbound' was authored on the child CRM and pulled in by
 * `CompanyChatSyncer`.
 *
 * @property int $id
 * @property int $company_id
 * @property string $uuid
 * @property string|null $external_id
 * @property string $direction
 * @property string $sender_type
 * @property int|null $sender_user_id
 * @property string|null $sender_name
 * @property string $body
 * @property string $status
 * @property string|null $failure_reason
 * @property Carbon $occurred_at
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id', 'uuid', 'external_id', 'direction', 'sender_type', 'sender_user_id',
    'sender_name', 'body', 'status', 'failure_reason', 'occurred_at', 'synced_at',
])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const SENDER_MASTER = 'master';

    public const SENDER_CHILD = 'child';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
