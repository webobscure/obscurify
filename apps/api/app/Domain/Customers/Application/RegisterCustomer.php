<?php

namespace App\Domain\Customers\Application;

use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\Customers\Enums\CustomerIdentityType;
use App\Domain\Customers\Enums\CustomerStatus;
use App\Domain\Customers\Mail\CustomerVerificationMail;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerIdentity;
use App\Domain\Customers\Models\CustomerSession;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Localization\LocaleContext;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Email/password registration. Reuses FindOrCreateCustomer's
 * store-scoped email lookup so a guest who checked out before creating
 * an account gets their prior order/return history attached to the new
 * identity, rather than starting from a second, disconnected Customer
 * row — the actual uniqueness guard against *duplicate registration* is
 * CustomerIdentity's (store_id, type, identifier) unique index, checked
 * explicitly below for a clean validation error instead of a DB
 * constraint violation.
 */
final class RegisterCustomer
{
    public function __construct(
        private readonly FindOrCreateCustomer $findOrCreateCustomer,
        private readonly IssueCustomerTokenPair $issueCustomerTokenPair,
        private readonly RequestEmailVerification $requestEmailVerification,
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly TenantContext $tenantContext,
        private readonly RecomputeCustomerMetrics $recomputeCustomerMetrics,
        private readonly LocaleContext $localeContext,
    ) {}

    /**
     * @param  array{email: string, password: string, first_name?: string|null, last_name?: string|null, phone?: string|null}  $data
     * @return array{customer: Customer, session: CustomerSession, access_token: string, refresh_token: string}
     */
    public function handle(array $data, ?string $ipAddress, ?string $userAgent): array
    {
        $identifier = Str::lower(trim($data['email']));

        $existingIdentity = CustomerIdentity::query()
            ->where('type', CustomerIdentityType::EmailPassword->value)
            ->where('identifier', $identifier)
            ->first();

        if ($existingIdentity !== null) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        [$customer, $session, $issued] = DB::transaction(function () use ($data, $identifier, $ipAddress, $userAgent) {
            $customer = $this->findOrCreateCustomer->handle([
                'email' => $identifier,
                'phone' => $data['phone'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]);

            $customer->fill([
                'first_name' => $data['first_name'] ?? $customer->first_name,
                'last_name' => $data['last_name'] ?? $customer->last_name,
                'phone' => $data['phone'] ?? $customer->phone,
                'status' => CustomerStatus::Active,
            ])->save();

            CustomerIdentity::query()->create([
                'customer_id' => $customer->id,
                'type' => CustomerIdentityType::EmailPassword->value,
                'identifier' => $identifier,
                'secret_hash' => Hash::make($data['password']),
            ]);

            $session = CustomerSession::query()->create([
                'customer_id' => $customer->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'last_used_at' => now(),
                'expires_at' => now()->addDays((int) config('customers.session_ttl_days')),
            ]);

            $issued = $this->issueCustomerTokenPair->handle($customer, $session);

            $this->recordOutboxEvent->handle('CustomerCreated', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'email' => $customer->email,
            ]);

            // Initializes CustomerMetric (all-zero for a brand new
            // account, non-zero for a guest-turned-account whose prior
            // orders just attached) and evaluates initial segment/tag
            // membership — a customer must never have no CustomerMetric
            // row at all.
            $this->recomputeCustomerMetrics->handle($customer->id);

            return [$customer, $session, $issued];
        });

        $verificationToken = $this->requestEmailVerification->handle($customer);

        // The request's own resolved locale (spec section 7) is the
        // best signal for a brand-new customer — nothing has been
        // saved to Customer.locale yet at registration time.
        Mail::to($identifier)
            ->locale($customer->locale ?? $this->localeContext->current())
            ->queue(new CustomerVerificationMail($verificationToken, $this->tenantContext->store()->name));

        return [
            'customer' => $customer,
            'session' => $session,
            'access_token' => $issued['access_token'],
            'refresh_token' => $issued['refresh_token'],
        ];
    }
}
