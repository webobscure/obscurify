<?php

namespace App\Shared\Commerce\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * See IdempotencyKeyStore for the claim/await protocol this table backs.
 *
 * @property string $id
 * @property string $store_id
 * @property string $operation
 * @property string $key
 * @property string|null $request_hash
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_body
 */
class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): IdempotencyKeyFactory
    {
        return IdempotencyKeyFactory::new();
    }

    protected $fillable = [
        'operation',
        'key',
        'request_hash',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
