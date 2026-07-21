<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $external_id
 * @property string $name
 * @property string|null $advertiser_type
 * @property string|null $url
 * @property string|null $api_key
 * @property bool $is_active
 * @property int|null $daily_cap
 * @property int|null $hourly_cap
 * @property string|null $default_deal_type
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id', 'external_id', 'name', 'advertiser_type', 'url', 'api_key',
    'is_active', 'daily_cap', 'hourly_cap', 'default_deal_type', 'meta', 'synced_at',
])]
#[Hidden(['api_key'])]
class Advertiser extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'meta' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
