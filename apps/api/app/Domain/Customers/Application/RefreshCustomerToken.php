<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Enums\CustomerTokenType;
use App\Domain\Customers\Exceptions\InvalidCredentialsException;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAccessToken;
use App\Domain\Customers\Models\CustomerSession;
use Illuminate\Support\Facades\DB;

/**
 * Refresh token rotation — mirrors RefreshAppToken. Every refresh
 * consumes the presented refresh token and issues a brand new
 * access+refresh pair, chained via rotated_from_id. Presenting a refresh
 * token that's already revoked (already spent by an earlier rotation)
 * is a reuse signal — the whole CustomerSession is revoked as a
 * precaution, forcing a fresh login rather than trusting anything
 * already issued to that session.
 */
final class RefreshCustomerToken
{
    public function __construct(
        private readonly IssueCustomerTokenPair $issueCustomerTokenPair,
        private readonly LogoutCustomer $logoutCustomer,
    ) {}

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    public function handle(string $refreshToken): array
    {
        $tokenHash = hash('sha256', $refreshToken);

        $outcome = DB::transaction(function () use ($tokenHash) {
            $token = CustomerAccessToken::query()
                ->where('token_hash', $tokenHash)
                ->where('type', CustomerTokenType::Refresh->value)
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                return ['ok' => false, 'reason' => 'invalid'];
            }

            if ($token->isRevoked()) {
                $session = CustomerSession::query()->find($token->customer_session_id);

                if ($session !== null) {
                    $this->logoutCustomer->handle($session);
                }

                return ['ok' => false, 'reason' => 'reused'];
            }

            if ($token->isExpired()) {
                return ['ok' => false, 'reason' => 'expired'];
            }

            $session = CustomerSession::query()->find($token->customer_session_id);

            if ($session === null || ! $session->isUsable()) {
                return ['ok' => false, 'reason' => 'invalid'];
            }

            $customer = Customer::query()->find($token->customer_id);

            if ($customer === null) {
                return ['ok' => false, 'reason' => 'invalid'];
            }

            $token->update(['revoked_at' => now()]);
            $issued = $this->issueCustomerTokenPair->handle($customer, $session, $token);

            return ['ok' => true, 'issued' => $issued];
        });

        if (! $outcome['ok']) {
            // Every rejection reason renders identically (invalid
            // credentials) — reused/expired/invalid are collapsed on
            // purpose so a caller can't distinguish "this token was
            // stolen and already used" from "this token never existed".
            throw InvalidCredentialsException::invalidCredentials();
        }

        return [
            'access_token' => $outcome['issued']['access_token'],
            'refresh_token' => $outcome['issued']['refresh_token'],
        ];
    }
}
