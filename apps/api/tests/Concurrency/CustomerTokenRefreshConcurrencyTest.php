<?php

use App\Domain\Customers\Application\RefreshCustomerToken;
use App\Domain\Customers\Enums\CustomerTokenType;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAccessToken;
use App\Domain\Customers\Models\CustomerSession;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Same fork-based real-concurrency pattern as
 * tests/Concurrency/AppTokenRefreshConcurrencyTest.php, applied to
 * CustomerAccessToken's identical rotation/reuse-detection logic (spec
 * section 17: "Concurrency"). Exactly one of two simultaneous refreshes
 * of the same refresh token may succeed; the loser's reuse detection
 * must still leave no usable token behind for the session.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->refreshTokenPlain = Str::random(64);

    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $session = CustomerSession::query()->create([
            'customer_id' => $customer->id,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        CustomerAccessToken::query()->create([
            'customer_id' => $customer->id,
            'customer_session_id' => $session->id,
            'type' => CustomerTokenType::Refresh->value,
            'token_hash' => hash('sha256', $this->refreshTokenPlain),
            'expires_at' => now()->addDays(30),
        ]);
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous refreshes of the same customer refresh token succeed, and revokes the session once reuse is detected', function () {
    $refresh = function () {
        return app(TenantContext::class)->scope($this->store, function () {
            $result = app(RefreshCustomerToken::class)->handle($this->refreshTokenPlain);

            return $result['access_token'];
        });
    };

    $results = runConcurrently([$refresh, $refresh]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    $failed = array_filter($results, fn ($r) => ! $r['ok']);

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    app(TenantContext::class)->scope($this->store, function () {
        expect(CustomerAccessToken::query()->whereNull('revoked_at')->count())->toBe(0);
        expect(CustomerSession::query()->whereNull('revoked_at')->count())->toBe(0);
    });
});
