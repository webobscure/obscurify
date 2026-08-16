<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Models\SearchSynonym;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Models\User;
use App\Shared\Localization\LocaleContext;
use App\Shared\Tenancy\TenantContext;

/**
 * Spec section 13: "Search architecture must become locale-aware.
 * Prepare synonym dictionaries per language." SearchSynonym.locale has
 * existed since Milestone 22 (structurally ready, never filtered on) —
 * see SynonymExpander's own updated docblock. No stemming/language
 * analyzers are implemented, matching the spec's explicit scope limit
 * — deliberately Latin-script terms here: SearchTextNormalizer's
 * `stripAccents()` runs every token through `iconv(...,
 * 'ASCII//TRANSLIT//IGNORE', ...)`, a pre-existing Milestone 22
 * behavior that discards non-Latin scripts entirely (a real, separate,
 * already-flagged limitation — see that class's own "Transliteration-
 * ready architecture" docblock — not something this milestone's scope
 * asks to fix). Testing locale-FILTERING specifically means picking
 * terms the normalizer can round-trip either way.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultSearchSetup::class)->handle($this->store);
    });
});

it('expands a locale-specific synonym only when the active locale matches', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Samsung Television']);
        app(BuildSearchDocument::class)->handle($product->fresh());

        SearchSynonym::query()->create(['term' => 'fernseher', 'synonyms' => ['television'], 'locale' => 'de']);

        $resultDe = app(LocaleContext::class)->scope('de', fn () => app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'fernseher')));
        expect($resultDe->total)->toBe(1);

        $resultRu = app(LocaleContext::class)->scope('ru', fn () => app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'fernseher')));
        expect($resultRu->total)->toBe(0);
    });
});

it('applies a locale-null synonym regardless of the active locale', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Samsung Television']);
        app(BuildSearchDocument::class)->handle($product->fresh());

        SearchSynonym::query()->create(['term' => 'tv', 'synonyms' => ['television'], 'locale' => null]);

        $resultRu = app(LocaleContext::class)->scope('ru', fn () => app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'tv')));
        expect($resultRu->total)->toBe(1);

        $resultDe = app(LocaleContext::class)->scope('de', fn () => app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'tv')));
        expect($resultDe->total)->toBe(1);
    });
});
