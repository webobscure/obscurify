<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Enums\CustomerTokenType;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAccessToken;
use App\Domain\Customers\Models\CustomerSession;
use Illuminate\Support\Str;

/**
 * Mints one access token + one refresh token for a Customer within a
 * given CustomerSession, always as a pair — mirrors
 * App\Domain\Apps\Application\IssueAppTokenPair exactly (see that class
 * for the rotation-chain rationale). Plaintext values are returned once;
 * only their SHA-256 hashes are persisted.
 */
final class IssueCustomerTokenPair
{
    /**
     * @return array{access_token: string, refresh_token: string, access: CustomerAccessToken, refresh: CustomerAccessToken}
     */
    public function handle(Customer $customer, CustomerSession $session, ?CustomerAccessToken $rotatedFromRefreshToken = null): array
    {
        $accessTokenPlain = Str::random(64);
        $refreshTokenPlain = Str::random(64);

        $access = CustomerAccessToken::query()->create([
            'customer_id' => $customer->id,
            'customer_session_id' => $session->id,
            'type' => CustomerTokenType::Access->value,
            'token_hash' => hash('sha256', $accessTokenPlain),
            'expires_at' => now()->addMinutes((int) config('customers.access_token_ttl_minutes')),
        ]);

        $refresh = CustomerAccessToken::query()->create([
            'customer_id' => $customer->id,
            'customer_session_id' => $session->id,
            'rotated_from_id' => $rotatedFromRefreshToken?->id,
            'type' => CustomerTokenType::Refresh->value,
            'token_hash' => hash('sha256', $refreshTokenPlain),
            'expires_at' => now()->addDays((int) config('customers.refresh_token_ttl_days')),
        ]);

        return [
            'access_token' => $accessTokenPlain,
            'refresh_token' => $refreshTokenPlain,
            'access' => $access,
            'refresh' => $refresh,
        ];
    }
}
