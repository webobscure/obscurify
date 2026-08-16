<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use RuntimeException;

/**
 * Per-request identity for the single POST /graphql endpoint — the
 * GraphQL analogue of TenantContext/CurrentCustomerContext/
 * CurrentAppContext combined, since resolvers need to know not just
 * "which store" but "who is calling and as what role" (spec section 5:
 * Guest/Customer/Merchant/App). Built once by GraphQLAuthenticator per
 * request and threaded through as the `context` value webonyx passes to
 * every resolver — never mutated after construction.
 */
final readonly class GraphQLContext
{
    public function __construct(
        public GraphQLActorType $actor,
        public Store $store,
        public ?User $user = null,
        public ?Customer $customer = null,
        public ?CustomerSession $customerSession = null,
        public ?InstalledApp $installedApp = null,
        public ?AppToken $appToken = null,
    ) {}

    public function isMerchant(): bool
    {
        return $this->actor === GraphQLActorType::Merchant;
    }

    public function isCustomer(): bool
    {
        return $this->actor === GraphQLActorType::Customer;
    }

    public function isApp(): bool
    {
        return $this->actor === GraphQLActorType::App;
    }

    public function isGuest(): bool
    {
        return $this->actor === GraphQLActorType::Guest;
    }

    /**
     * @throws RuntimeException when no customer is authenticated.
     */
    public function requireCustomer(): Customer
    {
        return $this->customer ?? throw new RuntimeException('No authenticated customer for this request.');
    }

    /**
     * @throws RuntimeException when no merchant user is authenticated.
     */
    public function requireMerchant(): User
    {
        return $this->user ?? throw new RuntimeException('No authenticated merchant for this request.');
    }
}
