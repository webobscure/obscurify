<?php

use App\Domain\Customers\Mail\CustomerPasswordResetMail;
use App\Domain\Customers\Mail\CustomerVerificationMail;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerActionToken;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-password.localhost';
    domainForStore($this->store, $this->host);
    $this->withoutMiddleware(ThrottleRequests::class);

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'password-test@example.test',
        'password' => 'original-password-1',
    ])->assertCreated();
});

it('sends a verification email on registration and verifies the account with the token', function () {
    Mail::assertQueued(CustomerVerificationMail::class);

    // Extract the plaintext token from the queued mailable itself,
    // since only its SHA-256 hash is ever persisted.
    $plainToken = null;
    Mail::assertQueued(CustomerVerificationMail::class, function (CustomerVerificationMail $mail) use (&$plainToken) {
        $plainToken = $mail->verificationToken;

        return true;
    });

    expect($plainToken)->toBeString();

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/verify-email'), ['token' => $plainToken])
        ->assertOk()
        ->assertJsonPath('data.verified_at', fn ($value) => $value !== null);

    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::query()->where('email', 'password-test@example.test')->firstOrFail();
        expect($customer->isVerified())->toBeTrue();
    });
});

it('rejects an already-used verification token', function () {
    $plainToken = null;
    Mail::assertQueued(CustomerVerificationMail::class, function (CustomerVerificationMail $mail) use (&$plainToken) {
        $plainToken = $mail->verificationToken;

        return true;
    });

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/verify-email'), ['token' => $plainToken])->assertOk();
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/verify-email'), ['token' => $plainToken])->assertStatus(422);
});

it('requests a password reset, resets the password, and revokes existing sessions', function () {
    $login = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'password-test@example.test',
        'password' => 'original-password-1',
    ])->assertOk();

    $oldAccessToken = $login->json('access_token');

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/password/forgot'), [
        'email' => 'password-test@example.test',
    ])->assertOk();

    Mail::assertQueued(CustomerPasswordResetMail::class);

    $plainToken = null;
    Mail::assertQueued(CustomerPasswordResetMail::class, function (CustomerPasswordResetMail $mail) use (&$plainToken) {
        $plainToken = $mail->resetToken;

        return true;
    });

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/password/reset'), [
        'token' => $plainToken,
        'password' => 'brand-new-password-1',
    ])->assertOk();

    // The pre-reset session is dead.
    $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account'), authHeader($oldAccessToken))
        ->assertStatus(401);

    // Old password no longer works; new one does.
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'password-test@example.test',
        'password' => 'original-password-1',
    ])->assertStatus(401);

    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/login'), [
        'email' => 'password-test@example.test',
        'password' => 'brand-new-password-1',
    ])->assertOk();
});

it('always returns success for forgot-password regardless of whether the email is registered, to avoid enumeration', function () {
    $known = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/password/forgot'), [
        'email' => 'password-test@example.test',
    ])->assertOk();

    $unknown = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/password/forgot'), [
        'email' => 'never-heard-of-them@example.test',
    ])->assertOk();

    expect($known->json('message'))->toBe($unknown->json('message'));

    app(TenantContext::class)->scope($this->store, function () {
        // Only the known email actually got a token created.
        expect(CustomerActionToken::query()->where('purpose', 'password_reset')->count())->toBe(1);
    });
});

it('rejects an expired or invalid reset token', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/password/reset'), [
        'token' => 'not-a-real-token',
        'password' => 'whatever-new-1',
    ])->assertStatus(422);
});
