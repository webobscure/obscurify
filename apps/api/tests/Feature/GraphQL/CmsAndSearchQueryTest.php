<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Cms\Application\PublishPageVersion;
use App\Domain\Cms\Models\Menu;
use App\Domain\Cms\Models\MenuItem;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Stores\Models\Store;
use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-cms.localhost';
    domainForStore($this->store, $this->host);
});

function scopedCms(Store $store, Closure $fn): mixed
{
    return app(TenantContext::class)->scope($store, $fn);
}

it('renders a published page and resolves the store\'s active menu tree', function () {
    scopedCms($this->store, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());

        $page = Page::query()->create(['title' => 'About Us', 'slug' => 'about-us', 'status' => 'draft']);
        $version = PageVersion::query()->create(['page_id' => $page->id, 'version_number' => 1, 'status' => 'draft', 'sections' => []]);
        app(PublishPageVersion::class)->handle($version);

        $menu = Menu::query()->create(['handle' => 'main', 'name' => 'Main Menu']);
        MenuItem::query()->create(['menu_id' => $menu->id, 'label' => 'Home', 'target_type' => 'url', 'url' => '/', 'position' => 0]);
    });

    $pageResponse = graphqlRequest($this->host, 'query { page(slug: "about-us") { title slug rendered } }');
    $pageResponse->assertOk();
    expect($pageResponse->json('data.page.title'))->toBe('About Us');
    expect($pageResponse->json('data.page.rendered'))->not->toBeNull();

    $missingPage = graphqlRequest($this->host, 'query { page(slug: "no-such-page") { title } }');
    expect($missingPage->json('data.page'))->toBeNull();
    expect($missingPage->json('errors.0.message'))->toBe('Page not found.');

    $menuResponse = graphqlRequest($this->host, 'query { navigation(handle: "main") { handle items { label url } } }');
    $menuResponse->assertOk();
    expect($menuResponse->json('data.navigation.items.0.label'))->toBe('Home');
});

it('resolves search and searchSuggestions through the exact Search & Discovery Platform pipeline', function () {
    scopedCms($this->store, function () {
        app(EnsureDefaultSearchSetup::class)->handle($this->store);
        $product = Product::factory()->create(['title' => 'Wireless Mouse', 'status' => 'active']);
        app(BuildSearchDocument::class)->handle($product->fresh());
    });

    $search = graphqlRequest($this->host, 'query { search(query: "wireless") { total items { title } searchQueryId } }');
    $search->assertOk();
    expect($search->json('data.search.total'))->toBe(1);
    expect($search->json('data.search.items.0.title'))->toBe('Wireless Mouse');
    expect($search->json('data.search.searchQueryId'))->toBeString();

    $suggestions = graphqlRequest($this->host, 'query { searchSuggestions(query: "wire") { products { title } } }');
    $suggestions->assertOk();
    expect($suggestions->json('data.searchSuggestions.products.0.title'))->toBe('Wireless Mouse');
});

it('caches the categories query across requests within the TTL, still store-scoped correctly', function () {
    scopedCms($this->store, fn () => Category::factory()->create(['title' => 'Cached Category', 'slug' => 'cached-category']));

    $first = graphqlRequest($this->host, 'query { categories { title } }');
    expect(collect($first->json('data.categories'))->pluck('title')->all())->toBe(['Cached Category']);

    // A category created after the first (now-cached) read must not
    // appear until the cache TTL expires — proves this is a real cache,
    // not an accidental no-op.
    scopedCms($this->store, fn () => Category::factory()->create(['title' => 'Not Yet Visible', 'slug' => 'not-yet-visible']));

    $second = graphqlRequest($this->host, 'query { categories { title } }');
    expect(collect($second->json('data.categories'))->pluck('title')->all())->toBe(['Cached Category']);
});
