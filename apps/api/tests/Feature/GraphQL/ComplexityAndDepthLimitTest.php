<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-limits.localhost';
    domainForStore($this->store, $this->host);
});

it('rejects a query nested deeper than the configured max depth', function () {
    // Category.children is recursively typed — nest it well past
    // QueryLimits::MAX_DEPTH (12) using only real schema fields.
    $inner = 'title';
    for ($i = 0; $i < 15; $i++) {
        $inner = "title children { {$inner} }";
    }
    $query = "query { categories { {$inner} } }";

    $response = graphqlRequest($this->host, $query);

    $response->assertOk();
    expect($response->json('data'))->toBeNull();
    expect(collect($response->json('errors'))->pluck('message')->implode(' '))->toContain('query depth');
});

it('rejects a query whose total field cost exceeds the configured max complexity', function () {
    // Every aliased field defaults to a complexity cost of 1 (see
    // QueryLimits) — 1500 aliases on one real, cheap scalar field
    // comfortably exceeds MAX_COMPLEXITY (1000) without needing any
    // fixture data to exist.
    $fields = collect(range(1, 1500))->map(fn ($i) => "f{$i}: name")->implode(' ');
    $query = "query { store { {$fields} } }";

    $response = graphqlRequest($this->host, $query);

    $response->assertOk();
    expect($response->json('data'))->toBeNull();
    expect(collect($response->json('errors'))->pluck('message')->implode(' '))->toContain('complexity');
});

it('allows an ordinary, shallow, low-complexity query through unaffected', function () {
    $response = graphqlRequest($this->host, 'query { store { id name } categories { title } }');

    $response->assertOk();
    expect($response->json('errors'))->toBeNull();
});

it('disables introspection when GRAPHQL_DISABLE_INTROSPECTION is enabled', function () {
    config(['graphql.disable_introspection' => true]);

    $response = graphqlRequest($this->host, 'query { __schema { queryType { name } } }');

    $response->assertOk();
    expect($response->json('data'))->toBeNull();
    expect(collect($response->json('errors'))->pluck('message')->implode(' '))->toContain('introspection');
});
