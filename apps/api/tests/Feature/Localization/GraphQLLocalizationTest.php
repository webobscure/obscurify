<?php

use App\Domain\Localization\Application\EnsureDefaultLanguages;
use App\Models\User;

/**
 * Spec section 6: "GraphQL error messages and validation should
 * respect the current locale." GraphQLController runs behind the same
 * global ResolveRequestLocale middleware as every other API route, so
 * this is mostly confirming GraphQLUserError's own factories route
 * through `__()` (see that class's own docblock) rather than a
 * separate localization mechanism.
 */
beforeEach(function () {
    app(EnsureDefaultLanguages::class)->handle();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    domainForStore($this->store, 'rc-graphql-locale.localhost');
});

it('renders a not-found GraphQL error in Russian when Accept-Language asks for it', function () {
    $response = graphqlRequest(
        'rc-graphql-locale.localhost',
        'query { product(slug: "not-a-real-slug") { id } }',
        headers: ['Accept-Language' => 'ru'],
    );

    expect($response->json('errors.0.message'))->toBe('Product не найден(а).');
});

it('renders the same error in German', function () {
    $response = graphqlRequest(
        'rc-graphql-locale.localhost',
        'query { product(slug: "not-a-real-slug") { id } }',
        headers: ['Accept-Language' => 'de'],
    );

    expect($response->json('errors.0.message'))->toBe('Product nicht gefunden.');
});

it('renders the same error in English with no Accept-Language override', function () {
    $response = graphqlRequest(
        'rc-graphql-locale.localhost',
        'query { product(slug: "not-a-real-slug") { id } }',
    );

    expect($response->json('errors.0.message'))->toBe('Product not found.');
});
