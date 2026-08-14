<?php

use App\Domain\Cms\Application\PublishPageVersion;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('resolves a page draft against the active theme, merging section defaults with instance overrides', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });

    $page = app(TenantContext::class)->scope($this->store, function () {
        $page = Page::query()->create(['title' => 'About Us', 'slug' => 'about-us', 'status' => 'draft']);
        $version = PageVersion::query()->create([
            'page_id' => $page->id,
            'version_number' => 1,
            'status' => 'draft',
            'sections' => [['id' => 'hero-1', 'section_handle' => 'hero', 'settings' => ['heading' => 'About our store'], 'blocks' => []]],
        ]);
        app(PublishPageVersion::class)->handle($version);

        return $page;
    });

    domainForStore($this->store, 'cms-render-test.localhost');

    $response = $this->getJson(storefrontUrl('cms-render-test.localhost', "/api/v1/storefront/pages/{$page->slug}"))->assertOk();

    expect($response->json('data.page.title'))->toBe('About Us')
        ->and($response->json('data.rendered.template'))->toBe('page')
        ->and($response->json('data.rendered.sections.0.handle'))->toBe('hero')
        ->and($response->json('data.rendered.sections.0.settings.heading'))->toBe('About our store')
        ->and($response->json('data.rendered.sections.0.settings.subheading'))->toBe('');
});

it('404s a draft page that was never published', function () {
    app(TenantContext::class)->scope($this->store, function () {
        Page::query()->create(['title' => 'Draft Only', 'slug' => 'draft-only', 'status' => 'draft']);
    });

    domainForStore($this->store, 'cms-draft-test.localhost');

    $this->getJson(storefrontUrl('cms-draft-test.localhost', '/api/v1/storefront/pages/draft-only'))->assertStatus(404);
});

it('carries the page version\'s SEO metadata into the storefront response', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });

    $page = app(TenantContext::class)->scope($this->store, fn () => Page::query()->create(['title' => 'About Us', 'slug' => 'about-us', 'status' => 'draft']));
    $draftVersionId = app(TenantContext::class)->scope(
        $this->store,
        fn () => PageVersion::query()->create(['page_id' => $page->id, 'version_number' => 1, 'status' => 'draft', 'sections' => []])->id,
    );

    // SEO must be set while the version is still a draft — a published
    // version is immutable, same as its sections.
    $this->actingAs($this->user, 'sanctum')->patchJson(
        "/api/v1/page-versions/{$draftVersionId}/seo",
        ['meta_title' => 'About Us | Retail', 'meta_description' => 'Learn more about us.'],
        tenantHeader($this->store),
    )->assertOk();

    app(TenantContext::class)->scope($this->store, fn () => app(PublishPageVersion::class)->handle(PageVersion::query()->findOrFail($draftVersionId)));

    domainForStore($this->store, 'cms-seo-test.localhost');

    $response = $this->getJson(storefrontUrl('cms-seo-test.localhost', "/api/v1/storefront/pages/{$page->slug}"))->assertOk();

    expect($response->json('data.seo.meta_title'))->toBe('About Us | Retail')
        ->and($response->json('data.seo.meta_description'))->toBe('Learn more about us.');
});
