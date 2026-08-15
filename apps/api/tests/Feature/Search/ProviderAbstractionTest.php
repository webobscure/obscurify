<?php

use App\Domain\Search\Exceptions\UnknownSearchProviderException;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Support\Providers\DatabaseSearchProvider;
use App\Domain\Search\Support\SearchProviderRegistry;

it('has the database provider registered by default', function () {
    $registry = app(SearchProviderRegistry::class);

    expect($registry->has(SearchProvider::DATABASE))->toBeTrue();
    expect($registry->resolve(SearchProvider::DATABASE))->toBeInstanceOf(DatabaseSearchProvider::class);
});

it('throws for an unregistered provider code', function () {
    $registry = app(SearchProviderRegistry::class);

    foreach (SearchProvider::FUTURE_CODES as $futureCode) {
        expect($registry->has($futureCode))->toBeFalse();
    }

    expect(fn () => $registry->resolve('meilisearch'))->toThrow(UnknownSearchProviderException::class);
});

it('the database provider reports its own code', function () {
    expect(app(DatabaseSearchProvider::class)->code())->toBe(SearchProvider::DATABASE);
});

it('the database provider\'s index/bulkIndex/delete are no-ops that never error', function () {
    $provider = app(DatabaseSearchProvider::class);
    $document = new SearchDocument;

    $provider->index($document);
    $provider->bulkIndex([$document]);
    $provider->delete('some-store-id', 'some-product-id');

    expect(true)->toBeTrue();
});
