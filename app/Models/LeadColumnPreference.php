<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property list<string> $hidden_columns
 * @property list<string>|null $column_order
 */
#[Fillable(['user_id', 'hidden_columns', 'column_order'])]
class LeadColumnPreference extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_columns' => 'array',
            'column_order' => 'array',
        ];
    }
}
