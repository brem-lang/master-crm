<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $assigned_to
 * @property string $external_id
 * @property string|null $request_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $mobile
 * @property string|null $country_code
 * @property string|null $ip_address
 * @property string|null $status
 * @property string|null $affiliate_name
 * @property bool $is_ftd
 * @property bool $ftd_released
 * @property string|null $offer_name
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $lead_created_at
 * @property Carbon|null $synced_at
 * @property Carbon|null $synced_to_parent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $advertiser_name
 * @property-read string|null $sale_status
 * @property-read string|null $live_lead_status
 */
#[Fillable([
    'company_id',
    'assigned_to',
    'external_id',
    'request_id',
    'first_name',
    'last_name',
    'email',
    'mobile',
    'country_code',
    'ip_address',
    'status',
    'affiliate_name',
    'is_ftd',
    'ftd_released',
    'offer_name',
    'meta',
    'lead_created_at',
    'synced_at',
    'synced_to_parent_at',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['advertiser_name', 'sale_status', 'live_lead_status'];

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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * `meta->lead_distributions` is a JSON array, which isn't portable to
     * query/aggregate in SQL across MySQL/SQLite — always read it off the
     * already-loaded `meta` attribute in PHP instead.
     *
     * @return list<array<string, mixed>>
     */
    public function distributions(): array
    {
        $distributions = $this->meta['lead_distributions'] ?? [];

        return is_array($distributions) ? $distributions : [];
    }

    /**
     * @return list<string>
     */
    public function advertiserNames(): array
    {
        return collect($this->distributions())
            ->map(fn ($distribution) => $distribution['advertisers']['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function primaryAdvertiserName(): ?string
    {
        return $this->advertiserNames()[0] ?? null;
    }

    public function getAdvertiserNameAttribute(): ?string
    {
        return $this->primaryAdvertiserName();
    }

    public function getSaleStatusAttribute(): ?string
    {
        $saleStatus = $this->meta['sale_status'] ?? null;

        return is_string($saleStatus) ? $saleStatus : null;
    }

    public function getLiveLeadStatusAttribute(): ?string
    {
        $score = $this->meta['live_lead_score'] ?? null;

        if (! is_numeric($score)) {
            return null;
        }

        return match (true) {
            $score >= 80 => 'green',
            $score >= 60 => 'orange',
            $score >= 40 => 'light-red',
            default => 'red',
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_ftd' => 'boolean',
            'ftd_released' => 'boolean',
            'meta' => 'array',
            'lead_created_at' => 'datetime',
            'synced_at' => 'datetime',
            'synced_to_parent_at' => 'datetime',
        ];
    }
}
