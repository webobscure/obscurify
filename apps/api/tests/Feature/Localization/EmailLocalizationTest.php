<?php

use App\Domain\Customers\Mail\CustomerPasswordResetMail;
use App\Domain\Customers\Mail\CustomerVerificationMail;
use App\Domain\Customers\Models\Customer;
use App\Domain\Localization\Application\EnsureDefaultLanguages;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;

/**
 * Spec section 12: "Email templates should support: Russian, English,
 * German." Two independent things are verified:
 *
 *  - that OUR code threads the right locale onto the Mailable — the
 *    `$mail->locale` property, set via the fluent
 *    `Mail::to()->locale()->queue()` chain (see CustomerVerificationMail's
 *    own docblock for why `Mail::fake()` + manually calling `$mail->build()`
 *    in a test would NOT exercise this correctly: build() outside
 *    Mailable::send()'s own `withLocale()` wrapper just uses whatever
 *    locale is ambient in the test process at that moment, which has
 *    nothing to do with what `$mail->locale` was set to).
 *  - that the lang files themselves produce genuinely different,
 *    correct text per locale — checked directly via `__()` with an
 *    explicit locale argument, independent of the Mailable/Mail::fake
 *    machinery entirely.
 */
beforeEach(function () {
    Mail::fake();
    app(EnsureDefaultLanguages::class)->handle();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-email-locale.localhost';
    domainForStore($this->store, $this->host);
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('queues the verification email in the request\'s resolved locale (Accept-Language)', function () {
    $this->withHeaders(['Accept-Language' => 'ru'])
        ->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
            'email' => 'ru-register@example.test',
            'password' => 'original-password-1',
        ])->assertCreated();

    Mail::assertQueued(CustomerVerificationMail::class, fn (CustomerVerificationMail $mail) => $mail->locale === 'ru');
});

it('queues the verification email in German when Accept-Language says so', function () {
    $this->withHeaders(['Accept-Language' => 'de'])
        ->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
            'email' => 'de-register@example.test',
            'password' => 'original-password-1',
        ])->assertCreated();

    Mail::assertQueued(CustomerVerificationMail::class, fn (CustomerVerificationMail $mail) => $mail->locale === 'de');
});

it('queues the password reset email in the customer\'s own saved locale, ahead of the request locale', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'saved-locale@example.test',
        'password' => 'original-password-1',
    ])->assertCreated();

    app(TenantContext::class)->scope($this->store, function () {
        Customer::query()->where('email', 'saved-locale@example.test')->firstOrFail()->update(['locale' => 'de']);
    });

    // The request itself asks for ru, but the customer's own saved
    // preference must win.
    $this->withHeaders(['Accept-Language' => 'ru'])
        ->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/password/forgot'), ['email' => 'saved-locale@example.test'])
        ->assertOk();

    Mail::assertQueued(CustomerPasswordResetMail::class, fn (CustomerPasswordResetMail $mail) => $mail->locale === 'de');
});

it('falls back to the request locale when the customer has no saved preference', function () {
    $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'no-pref@example.test',
        'password' => 'original-password-1',
    ])->assertCreated();

    $this->withHeaders(['Accept-Language' => 'de'])
        ->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/password/forgot'), ['email' => 'no-pref@example.test'])
        ->assertOk();

    Mail::assertQueued(CustomerPasswordResetMail::class, fn (CustomerPasswordResetMail $mail) => $mail->locale === 'de');
});

it('produces genuinely different, correct subject text per locale in the lang files themselves', function () {
    expect(__('emails.verify_email.subject', ['store' => 'Acme'], 'ru'))->toBe('Подтвердите ваш email — Acme')
        ->and(__('emails.verify_email.subject', ['store' => 'Acme'], 'en'))->toBe('Verify your email — Acme')
        ->and(__('emails.verify_email.subject', ['store' => 'Acme'], 'de'))->toBe('Bestätigen Sie Ihre E-Mail-Adresse — Acme')
        ->and(__('emails.password_reset.subject', ['store' => 'Acme'], 'ru'))->toBe('Сброс пароля — Acme')
        ->and(__('emails.password_reset.subject', ['store' => 'Acme'], 'en'))->toBe('Reset your password — Acme')
        ->and(__('emails.password_reset.subject', ['store' => 'Acme'], 'de'))->toBe('Passwort zurücksetzen — Acme');
});
