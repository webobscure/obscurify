<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Models\SearchSynonym;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Domain\Search\Support\SearchAnalyticsSummary;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    app(TenantContext::class)->scope($this->storeA, fn () => app(EnsureDefaultSearchSetup::class)->handle($this->storeA));
    app(TenantContext::class)->scope($this->storeB, fn () => app(EnsureDefaultSearchSetup::class)->handle($this->storeB));
});

it('never lets one store\'s indexed products appear in another store\'s search results', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $product = Product::factory()->create(['title' => 'Store A Exclusive Widget']);
        app(BuildSearchDocument::class)->handle($product->fresh());
    });

    $resultB = app(TenantContext::class)->scope($this->storeB, fn () => app(ExecuteSearch::class)->handle($this->storeB, new ExecuteSearchRequest(queryText: 'widget')));
    expect($resultB->total)->toBe(0);

    $resultA = app(TenantContext::class)->scope($this->storeA, fn () => app(ExecuteSearch::class)->handle($this->storeA, new ExecuteSearchRequest(queryText: 'widget')));
    expect($resultA->total)->toBe(1);
});

it('never lets one store\'s synonym expand another store\'s search, even for an identical term', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $tv = Product::factory()->create(['title' => 'Samsung Television']);
        app(BuildSearchDocument::class)->handle($tv->fresh());
        SearchSynonym::query()->create(['term' => 'tv', 'synonyms' => ['television']]);
    });

    app(TenantContext::class)->scope($this->storeB, function () {
        $tv = Product::factory()->create(['title' => 'Samsung Television']);
        app(BuildSearchDocument::class)->handle($tv->fresh());
    });

    $resultB = app(TenantContext::class)->scope($this->storeB, fn () => app(ExecuteSearch::class)->handle($this->storeB, new ExecuteSearchRequest(queryText: 'tv')));

    // Store B has no "tv" synonym of its own, so "tv" must not expand to
    // "television" for it even though Store A defines that exact term.
    expect($resultB->total)->toBe(0);
});

it('never lists, shows, updates, or deletes another store\'s search synonyms, rules, or pinned results', function () {
    $synonymA = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/search-synonyms', [
        'term' => 'tv', 'synonyms' => ['television'],
    ], tenantHeader($this->storeA))->json('data.id');

    $listB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/search-synonyms', tenantHeader($this->storeB));
    $listB->assertOk()->assertJsonCount(0, 'data');

    $updateB = $this->actingAs($this->userB, 'sanctum')->patchJson("/api/v1/search-synonyms/{$synonymA}", ['term' => 'hacked'], tenantHeader($this->storeB));
    $updateB->assertNotFound();

    $deleteB = $this->actingAs($this->userB, 'sanctum')->deleteJson("/api/v1/search-synonyms/{$synonymA}", [], tenantHeader($this->storeB));
    $deleteB->assertNotFound();

    $product = app(TenantContext::class)->scope($this->storeB, fn () => Product::factory()->create());

    $ruleA = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/search-rules', [
        'name' => 'Boost', 'action' => 'boost', 'product_id' => $product->id, 'boost_amount' => 10,
    ], tenantHeader($this->storeA))->json('data.id');

    $listRulesB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/search-rules', tenantHeader($this->storeB));
    $listRulesB->assertOk()->assertJsonCount(0, 'data');

    $showRuleB = $this->actingAs($this->userB, 'sanctum')->patchJson("/api/v1/search-rules/{$ruleA}", ['name' => 'Hacked'], tenantHeader($this->storeB));
    $showRuleB->assertNotFound();

    $pinnedA = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pinned-search-results', [
        'keyword' => 'widget', 'product_id' => $product->id,
    ], tenantHeader($this->storeA))->json('data.id');

    $listPinnedB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/pinned-search-results', tenantHeader($this->storeB));
    $listPinnedB->assertOk()->assertJsonCount(0, 'data');

    $deletePinnedB = $this->actingAs($this->userB, 'sanctum')->deleteJson("/api/v1/pinned-search-results/{$pinnedA}", [], tenantHeader($this->storeB));
    $deletePinnedB->assertNotFound();
});

it('never shows another store\'s search index status or search settings', function () {
    $showIndexB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/search-index', tenantHeader($this->storeB));
    $showIndexB->assertOk();
    expect($showIndexB->json('data.document_count'))->toBe(0);

    app(TenantContext::class)->scope($this->storeA, function () {
        $product = Product::factory()->create();
        app(BuildSearchDocument::class)->handle($product->fresh());
    });

    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/search-index/reindex', [], tenantHeader($this->storeA));

    $showIndexBAfter = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/search-index', tenantHeader($this->storeB));
    expect($showIndexBAfter->json('data.document_count'))->toBe(0);

    $settingsB = $this->actingAs($this->userB, 'sanctum')->patchJson('/api/v1/search-settings', ['results_per_page' => 5], tenantHeader($this->storeB));
    $settingsB->assertOk();

    $settingsA = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/search-settings', tenantHeader($this->storeA));
    expect($settingsA->json('data.results_per_page'))->not->toBe(5);
});

it('never lets one store\'s search analytics summary include another store\'s search activity', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $product = Product::factory()->create(['title' => 'Alpha Product']);
        app(BuildSearchDocument::class)->handle($product->fresh());
        app(ExecuteSearch::class)->handle($this->storeA, new ExecuteSearchRequest(queryText: 'alpha'));
    });

    $summaryB = app(TenantContext::class)->scope($this->storeB, fn () => app(SearchAnalyticsSummary::class)->build($this->storeB->id, now()->subDay(), now()->addDay()));

    expect($summaryB['total_searches'])->toBe(0);
});
