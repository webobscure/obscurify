<?php

use App\Domain\Cms\Application\PublishPageVersion;
use App\Domain\Cms\Models\Blog;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Cms\Models\Redirect;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->redirectB = app(TenantContext::class)->scope($this->storeB, fn () => Redirect::query()->create(['from_path' => '/old', 'to_path' => '/new', 'status_code' => 301]));
});

it('creates a redirect and resolves it on the storefront', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/redirects', [
        'from_path' => '/old-page', 'to_path' => '/pages/new-page',
    ], tenantHeader($this->storeA))->assertCreated()
        ->assertJsonPath('data.status_code', 301);

    domainForStore($this->storeA, 'cms-redirect-test.localhost');

    $response = $this->getJson(storefrontUrl('cms-redirect-test.localhost', '/api/v1/storefront/redirect?path=/old-page'))->assertOk();
    expect($response->json('data.to_path'))->toBe('/pages/new-page')
        ->and($response->json('data.status_code'))->toBe(301);

    $this->getJson(storefrontUrl('cms-redirect-test.localhost', '/api/v1/storefront/redirect?path=/never-existed'))->assertStatus(404);
});

it('rejects a from_path that does not start with a slash', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/redirects', [
        'from_path' => 'old-page', 'to_path' => '/pages/new-page',
    ], tenantHeader($this->storeA))->assertStatus(422);
});

it('never lets Store A read or edit a Store B redirect', function () {
    domainForStore($this->storeA, 'cms-redirect-isolation-test.localhost');

    // Store B's redirect must never resolve on Store A's storefront.
    $this->getJson(storefrontUrl('cms-redirect-isolation-test.localhost', '/api/v1/storefront/redirect?path=/old'))->assertStatus(404);

    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/redirects/{$this->redirectB->id}", ['to_path' => '/hijacked'], tenantHeader($this->storeA))->assertNotFound();
});

it('lists published pages and blog posts in the sitemap, never drafts', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $page = Page::query()->create(['title' => 'About', 'slug' => 'about', 'status' => 'draft']);
        $version = PageVersion::query()->create(['page_id' => $page->id, 'version_number' => 1, 'status' => 'draft', 'sections' => []]);
        app(PublishPageVersion::class)->handle($version);

        Page::query()->create(['title' => 'Unpublished', 'slug' => 'unpublished', 'status' => 'draft']);

        $blog = Blog::query()->create(['title' => 'News', 'slug' => 'news']);
        $blog->posts()->create(['title' => 'Live', 'slug' => 'live-post', 'body' => 'x', 'status' => 'published', 'published_at' => now()]);
        $blog->posts()->create(['title' => 'Draft', 'slug' => 'draft-post', 'body' => 'x', 'status' => 'draft']);
    });

    domainForStore($this->storeA, 'cms-sitemap-test.localhost', ['is_primary' => true]);

    $response = $this->get(storefrontUrl('cms-sitemap-test.localhost', '/api/v1/storefront/sitemap.xml'))->assertOk();

    $xml = $response->getContent();
    expect($xml)->toContain('cms-sitemap-test.localhost/pages/about')
        ->and($xml)->toContain('cms-sitemap-test.localhost/blog/posts/live-post')
        ->and($xml)->not->toContain('/pages/unpublished')
        ->and($xml)->not->toContain('/blog/posts/draft-post');
});
