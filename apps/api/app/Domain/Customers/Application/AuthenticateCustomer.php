<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Enums\CustomerIdentityType;
use App\Domain\Customers\Enums\CustomerStatus;
use App\Domain\Customers\Exceptions\InvalidCredentialsException;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerIdentity;
use App\Domain\Customers\Models\CustomerSession;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Email/password login. Account lock protection (spec section 3): a
 * CustomerIdentity locks itself for config('customers.lockout_minutes')
 * after config('customers.max_failed_attempts') consecutive failures,
 * and a locked identity rejects even a *correct* password until the
 * lock expires — the point of the lock is to make offline/online
 * brute-forcing expensive regardless of whether the attacker eventually
 * guesses right.
 */
final class AuthenticateCustomer
{
    public function __construct(
        private readonly IssueCustomerTokenPair $issueCustomerTokenPair,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @return array{customer: Customer, session: CustomerSession, access_token: string, refresh_token: string}
     */
    /**
     * A failed attempt's counter increment must survive even though the
     * request itself ends in an error — so, like RefreshAppToken, the
     * rejection is thrown *after* DB::transaction() returns, never from
     * inside it (throwing inside would roll the increment back along
     * with everything else).
     */
    public function handle(string $email, string $password, ?string $ipAddress, ?string $userAgent): array
    {
        $identifier = Str::lower(trim($email));

        $outcome = DB::transaction(function () use ($identifier, $password, $ipAddress, $userAgent) {
            $identity = CustomerIdentity::query()
                ->where('type', CustomerIdentityType::EmailPassword->value)
                ->where('identifier', $identifier)
                ->lockForUpdate()
                ->first();

            if ($identity === null) {
                // Still hash-check against a dummy value so a
                // non-existent-email response takes roughly the same
                // time as a wrong-password response (timing side channel).
                Hash::check($password, '$2y$10$'.str_repeat('a', 53));

                return ['ok' => false, 'reason' => 'invalid'];
            }

            if ($identity->isLocked()) {
                return ['ok' => false, 'reason' => 'locked'];
            }

            if (! Hash::check($password, $identity->secret_hash)) {
                $this->registerFailedAttempt($identity);

                return ['ok' => false, 'reason' => 'invalid'];
            }

            $customer = Customer::query()->findOrFail($identity->customer_id);

            if ($customer->status !== CustomerStatus::Active) {
                return ['ok' => false, 'reason' => 'invalid'];
            }

            if ($identity->failed_attempts > 0 || $identity->locked_until !== null) {
                $identity->update(['failed_attempts' => 0, 'locked_until' => null]);
            }

            $session = CustomerSession::query()->create([
                'customer_id' => $customer->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'last_used_at' => now(),
                'expires_at' => now()->addDays((int) config('customers.session_ttl_days')),
            ]);

            $issued = $this->issueCustomerTokenPair->handle($customer, $session);

            $this->recordOutboxEvent->handle('CustomerLoggedIn', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'customer_session_id' => $session->id,
            ]);

            return [
                'ok' => true,
                'customer' => $customer,
                'session' => $session,
                'access_token' => $issued['access_token'],
                'refresh_token' => $issued['refresh_token'],
            ];
        });

        if (! $outcome['ok']) {
            throw match ($outcome['reason']) {
                'locked' => InvalidCredentialsException::accountLocked(),
                default => InvalidCredentialsException::invalidCredentials(),
            };
        }

        return $outcome;
    }

    private function registerFailedAttempt(CustomerIdentity $identity): void
    {
        $attempts = $identity->failed_attempts + 1;
        $maxAttempts = (int) config('customers.max_failed_attempts');

        $identity->update([
            'failed_attempts' => $attempts,
            'locked_until' => $attempts >= $maxAttempts
                ? now()->addMinutes((int) config('customers.lockout_minutes'))
                : $identity->locked_until,
        ]);
    }
}
