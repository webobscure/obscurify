<?php

use App\Domain\GraphQL\Extensions\Examples\AppHealthExtension;
use App\Domain\GraphQL\Extensions\GraphQLExtensionRegistry;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-extensions.localhost';
    domainForStore($this->store, $this->host);
});

it('registers AppHealthExtension into the real GraphQLExtensionRegistry singleton, not just as a hardcoded field', function () {
    $registry = app(GraphQLExtensionRegistry::class);

    expect(collect($registry->all())->contains(fn ($extension) => $extension instanceof AppHealthExtension))->toBeTrue();
});

it('exposes an extension-contributed field (appHealth) and type (AppHealthStatus) through real schema introspection', function () {
    $fieldIntrospection = graphqlRequest($this->host, 'query { __type(name: "Query") { fields { name } } }');
    $fieldNames = collect($fieldIntrospection->json('data.__type.fields'))->pluck('name')->all();

    expect($fieldNames)->toContain('appHealth');

    $typeIntrospection = graphqlRequest($this->host, 'query { __type(name: "AppHealthStatus") { name fields { name } } }');
    expect($typeIntrospection->json('data.__type.name'))->toBe('AppHealthStatus');
    expect(collect($typeIntrospection->json('data.__type.fields'))->pluck('name')->all())->toBe(['status', 'appName', 'checkedAt']);
});

it('declares the @auth directive in the schema for introspection', function () {
    $response = graphqlRequest($this->host, 'query { __schema { directives { name args { name } } } }');

    $names = collect($response->json('data.__schema.directives'))->pluck('name')->all();
    expect($names)->toContain('auth');
});
