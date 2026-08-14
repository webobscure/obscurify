<?php

namespace App\Domain\Apps\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Also deliberately not `BelongsToTenant` — resolved by `client_id`
 * during the OAuth flow, before any tenant is known (mirrors
 * PaymentWebhookEvent's same reasoning for provider webhooks).
 *
 * @property string $id
 * @property string $app_id
 * @property string $client_id
 * @property string $client_secret_hash
 */
class OAuthClient extends Model
{
    use HasUlids;

    protected $table = 'oauth_clients';

    protected $fillable = [
        'app_id',
        'client_id',
        'client_secret_hash',
    ];

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
