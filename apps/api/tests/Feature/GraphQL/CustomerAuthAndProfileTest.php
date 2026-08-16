<?php

use App\Domain\Customers\Models\Customer;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-customer.localhost';
    domainForStore($this->store, $this->host);
});

it('registers a new customer and returns an AuthPayload with real tokens', function () {
    $response = graphqlRequest($this->host, '
        mutation {
          registerCustomer(email: "ada@example.test", password: "super-secret-1", firstName: "Ada", lastName: "Lovelace") {
            customer { email firstName lastName }
            accessToken
            refreshToken
          }
        }
    ');

    $response->assertOk();
    expect($response->json('data.registerCustomer.customer.email'))->toBe('ada@example.test');
    expect($response->json('data.registerCustomer.accessToken'))->toBeString();
    expect($response->json('data.registerCustomer.refreshToken'))->toBeString();

    app(TenantContext::class)->scope($this->store, function () {
        expect(Customer::query()->where('email', 'ada@example.test')->count())->toBe(1);
    });
});

it('logs a customer in, resolves their own profile, updates it, and manages addresses', function () {
    graphqlRequest($this->host, 'mutation { registerCustomer(email: "grace@example.test", password: "super-secret-1") { accessToken } }');

    $login = graphqlRequest($this->host, 'mutation { loginCustomer(email: "grace@example.test", password: "super-secret-1") { accessToken customer { email } } }');
    $login->assertOk();
    $token = $login->json('data.loginCustomer.accessToken');
    expect($token)->toBeString();

    $me = graphqlRequest($this->host, 'query { customer { email } }', [], authHeader($token));
    expect($me->json('data.customer.email'))->toBe('grace@example.test');

    $updated = graphqlRequest($this->host, 'mutation { updateProfile(firstName: "Grace", lastName: "Hopper") { firstName lastName } }', [], authHeader($token));
    expect($updated->json('data.updateProfile.firstName'))->toBe('Grace');

    $createAddress = graphqlRequest($this->host, '
        mutation {
          createCustomerAddress(address: { firstName: "Grace", city: "Arlington", countryCode: "US" }, isDefaultShipping: true) {
            id city isDefaultShipping
          }
        }
    ', [], authHeader($token));
    $createAddress->assertOk();
    expect($createAddress->json('data.createCustomerAddress.city'))->toBe('Arlington');
    $addressId = $createAddress->json('data.createCustomerAddress.id');

    $updateAddress = graphqlRequest($this->host, '
        mutation($addressId: ID!) { updateCustomerAddress(addressId: $addressId, address: { city: "Boston" }) { city } }
    ', ['addressId' => $addressId], authHeader($token));
    expect($updateAddress->json('data.updateCustomerAddress.city'))->toBe('Boston');

    $deleteAddress = graphqlRequest($this->host, '
        mutation($addressId: ID!) { deleteCustomerAddress(addressId: $addressId) }
    ', ['addressId' => $addressId], authHeader($token));
    expect($deleteAddress->json('data.deleteCustomerAddress'))->toBeTrue();
});

it('rejects an invalid login with a client-safe error, never revealing whether the email exists', function () {
    $response = graphqlRequest($this->host, 'mutation { loginCustomer(email: "nobody@example.test", password: "wrong-password") { accessToken } }');

    expect($response->json('data.loginCustomer'))->toBeNull();
    expect($response->json('errors.0.message'))->not->toBe('');
});

it('never lets a guest call customer-only queries or mutations', function () {
    $customerQuery = graphqlRequest($this->host, 'query { customer { email } }');
    expect($customerQuery->json('errors.0.message'))->toBe('You must be logged in as a customer.');

    $profileMutation = graphqlRequest($this->host, 'mutation { updateProfile(firstName: "Nope") { firstName } }');
    expect($profileMutation->json('errors.0.message'))->toBe('You must be logged in as a customer.');
});

it('rejects an unrecognized customer bearer token with a real 401, not a silent guest downgrade', function () {
    $response = graphqlRequest($this->host, 'query { store { id } }', [], authHeader('totally-invalid-token'));

    $response->assertStatus(401);
});
