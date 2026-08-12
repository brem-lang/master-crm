<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $external_id
 * @property string|null $affiliate_id
 * @property string|null $advertiser_id
 * @property string|null $country_code
 * @property int|null $weight
 * @property int|null $daily_cap
 * @property int|null $hourly_cap
 * @property bool $is_active
 * @property string|null $priority_type
 * @property int|null $priority
 * @property string|null $start_time
 * @property string|null $end_time
 * @property array<string, mixed>|null $weekly_schedule
 * @property string|null $timezone
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id', 'external_id', 'affiliate_id', 'advertiser_id', 'country_code',
    'weight', 'daily_cap', 'hourly_cap', 'is_active', 'priority_type', 'priority',
    'start_time', 'end_time', 'weekly_schedule', 'timezone', 'meta', 'synced_at',
])]
class DistributionRule extends Model
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
            'is_active' => 'boolean',
            'weekly_schedule' => 'array',
            'meta' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
