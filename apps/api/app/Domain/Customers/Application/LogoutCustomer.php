<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\CustomerSession;

/**
 * Logout revokes the whole CustomerSession (the device), not just the
 * access token that authenticated the request — so its already-issued
 * refresh token stops working too, rather than remaining silently valid
 * until it naturally expires.
 */
final class LogoutCustomer
{
    public function handle(CustomerSession $session): void
    {
        if ($session->isRevoked()) {
            return;
        }

        $session->update(['revoked_at' => now()]);

        $session->accessTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
