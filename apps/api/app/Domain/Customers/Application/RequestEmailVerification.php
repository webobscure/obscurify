<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Enums\CustomerActionTokenPurpose;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerActionToken;
use Illuminate\Support\Str;

/**
 * Invalidates any previously-issued, still-unused verification token for
 * this customer before minting a new one — only the most recently sent
 * link should ever work.
 */
final class RequestEmailVerification
{
    public function handle(Customer $customer): string
    {
        CustomerActionToken::query()
            ->where('customer_id', $customer->id)
            ->where('purpose', CustomerActionTokenPurpose::EmailVerification->value)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $plainToken = Str::random(48);

        CustomerActionToken::query()->create([
            'customer_id' => $customer->id,
            'purpose' => CustomerActionTokenPurpose::EmailVerification->value,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours((int) config('customers.email_verification_ttl_hours')),
        ]);

        return $plainToken;
    }
}
