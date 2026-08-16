<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Enums\CustomerActionTokenPurpose;
use App\Domain\Customers\Enums\CustomerIdentityType;
use App\Domain\Customers\Mail\CustomerPasswordResetMail;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerActionToken;
use App\Domain\Customers\Models\CustomerIdentity;
use App\Shared\Localization\LocaleContext;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Always succeeds from the caller's point of view whether or not the
 * email is registered — the response must never reveal which (spec
 * section 3: this is the account-enumeration defense for the "forgot
 * password" surface specifically, since login already avoids it via
 * InvalidCredentialsException's single message).
 */
final class RequestPasswordReset
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LocaleContext $localeContext,
    ) {}

    public function handle(string $email): void
    {
        $identifier = Str::lower(trim($email));

        $identity = CustomerIdentity::query()
            ->where('type', CustomerIdentityType::EmailPassword->value)
            ->where('identifier', $identifier)
            ->first();

        if ($identity === null) {
            return;
        }

        CustomerActionToken::query()
            ->where('customer_id', $identity->customer_id)
            ->where('purpose', CustomerActionTokenPurpose::PasswordReset->value)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $plainToken = Str::random(48);

        CustomerActionToken::query()->create([
            'customer_id' => $identity->customer_id,
            'purpose' => CustomerActionTokenPurpose::PasswordReset->value,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes((int) config('customers.password_reset_ttl_minutes')),
        ]);

        $customerLocale = Customer::query()->find($identity->customer_id)?->locale;

        Mail::to($identifier)
            ->locale($customerLocale ?? $this->localeContext->current())
            ->queue(new CustomerPasswordResetMail($plainToken, $this->tenantContext->store()->name));
    }
}
