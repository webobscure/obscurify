<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Enums\CustomerActionTokenPurpose;
use App\Domain\Customers\Enums\CustomerIdentityType;
use App\Domain\Customers\Exceptions\InvalidActionTokenException;
use App\Domain\Customers\Models\CustomerAccessToken;
use App\Domain\Customers\Models\CustomerActionToken;
use App\Domain\Customers\Models\CustomerIdentity;
use App\Domain\Customers\Models\CustomerSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Consuming a valid reset token also clears any lockout and revokes
 * every existing session/token for the customer — proving possession of
 * the mailbox is a strong enough signal to both lift a lock (the
 * legitimate owner regaining access) and forcibly sign out whoever else
 * might be holding a live session (the standard response to "the
 * password was just changed").
 */
final class ResetPassword
{
    public function handle(string $plainToken, string $newPassword): void
    {
        $tokenHash = hash('sha256', $plainToken);

        DB::transaction(function () use ($tokenHash, $newPassword) {
            $token = CustomerActionToken::query()
                ->where('token_hash', $tokenHash)
                ->where('purpose', CustomerActionTokenPurpose::PasswordReset->value)
                ->lockForUpdate()
                ->first();

            if ($token === null || ! $token->isUsable()) {
                throw InvalidActionTokenException::make();
            }

            $token->update(['used_at' => now()]);

            $identity = CustomerIdentity::query()
                ->where('customer_id', $token->customer_id)
                ->where('type', CustomerIdentityType::EmailPassword->value)
                ->first();

            if ($identity === null) {
                throw InvalidActionTokenException::make();
            }

            $identity->update([
                'secret_hash' => Hash::make($newPassword),
                'failed_attempts' => 0,
                'locked_until' => null,
            ]);

            $now = now();
            $activeSessionIds = CustomerSession::query()
                ->where('customer_id', $token->customer_id)
                ->whereNull('revoked_at')
                ->pluck('id');

            CustomerSession::query()
                ->whereIn('id', $activeSessionIds)
                ->update(['revoked_at' => $now]);

            CustomerAccessToken::query()
                ->whereIn('customer_session_id', $activeSessionIds)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);
        });
    }
}
