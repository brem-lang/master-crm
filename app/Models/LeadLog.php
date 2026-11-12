<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string $offer_name
 * @property array<array-key, mixed> $data
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'offer_name', 'data', 'ip_address'])]
class LeadLog extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
