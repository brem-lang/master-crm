<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $website
 * @property string $api_url
 * @property string $api_key
 * @property string|null $leads_count_url
 * @property string|null $affiliates_url
 * @property string|null $advertisers_url
 * @property string|null $affiliate_count_api_url
 * @property string|null $advertiser_count_api_url
 * @property string|null $send_test_lead_url
 * @property string|null $release_ftd_url
 * @property string|null $send_lead_url
 * @property string|null $update_affiliate_status_url
 * @property string|null $distribution_rules_url
 * @property bool $is_active
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $last_synced_since
 * @property string|null $last_synced_cursor
 * @property Carbon|null $affiliates_last_synced_at
 * @property Carbon|null $affiliates_last_synced_since
 * @property string|null $affiliates_last_synced_cursor
 * @property Carbon|null $advertisers_last_synced_at
 * @property Carbon|null $advertisers_last_synced_since
 * @property string|null $advertisers_last_synced_cursor
 * @property Carbon|null $distribution_rules_last_synced_at
 * @property Carbon|null $distribution_rules_last_synced_since
 * @property string|null $distribution_rules_last_synced_cursor
 * @property array<string, mixed>|null $db_connection
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'slug', 'website', 'api_url', 'api_key', 'leads_count_url', 'affiliates_url', 'advertisers_url',
    'affiliate_count_api_url', 'advertiser_count_api_url', 'send_test_lead_url', 'release_ftd_url', 'send_lead_url',
    'update_affiliate_status_url', 'distribution_rules_url',
    'is_active', 'last_synced_at', 'last_synced_since', 'last_synced_cursor',
    'affiliates_last_synced_at', 'affiliates_last_synced_since', 'affiliates_last_synced_cursor',
    'advertisers_last_synced_at', 'advertisers_last_synced_since', 'advertisers_last_synced_cursor',
    'distribution_rules_last_synced_at', 'distribution_rules_last_synced_since', 'distribution_rules_last_synced_cursor',
])]
#[Hidden(['api_key'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<Affiliate, $this>
     */
    public function affiliates(): HasMany
    {
        return $this->hasMany(Affiliate::class);
    }

    /**
     * @return HasMany<Advertiser, $this>
     */
    public function advertisers(): HasMany
    {
        return $this->hasMany(Advertiser::class);
    }

    /**
     * @return HasMany<DistributionRule, $this>
     */
    public function distributionRules(): HasMany
    {
        return $this->hasMany(DistributionRule::class);
    }

    /**
     * Derive a unique slug from a company name, appending -2, -3, ... on collision.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($suffix = 2; static::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'db_connection' => 'array',
            'api_key' => 'encrypted',
            'last_synced_at' => 'datetime',
            'last_synced_since' => 'datetime',
            'affiliates_last_synced_at' => 'datetime',
            'affiliates_last_synced_since' => 'datetime',
            'advertisers_last_synced_at' => 'datetime',
            'advertisers_last_synced_since' => 'datetime',
            'distribution_rules_last_synced_at' => 'datetime',
            'distribution_rules_last_synced_since' => 'datetime',
        ];
    }
}
