<?php

use App\Domain\Cms\Models\Blog;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->blogB = app(TenantContext::class)->scope($this->storeB, fn () => Blog::query()->create(['title' => 'Store B Blog', 'slug' => 'news']));
});

it('creates a blog, adds a draft post, and publishes it', function () {
    $blog = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/blogs', [
        'title' => 'News', 'slug' => 'news',
    ], tenantHeader($this->storeA))->assertCreated();

    $post = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/blogs/{$blog->json('data.id')}/posts",
        ['title' => 'Hello World', 'slug' => 'hello-world', 'body' => 'Our first post.'],
        tenantHeader($this->storeA),
    )->assertCreated();

    expect($post->json('data.status'))->toBe('draft');

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/blog-posts/{$post->json('data.id')}", tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.title', 'Hello World');

    $published = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/blog-posts/{$post->json('data.id')}/publish",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    expect($published->json('data.status'))->toBe('published')
        ->and($published->json('data.published_at'))->not->toBeNull();
});

it('creates a post with a future scheduled_at as status scheduled', function () {
    $blog = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/blogs', [
        'title' => 'News', 'slug' => 'news',
    ], tenantHeader($this->storeA))->assertCreated();

    $post = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/blogs/{$blog->json('data.id')}/posts",
        ['title' => 'Coming Soon', 'slug' => 'coming-soon', 'body' => 'Soon.', 'scheduled_at' => now()->addDay()->toIso8601String()],
        tenantHeader($this->storeA),
    )->assertCreated();

    expect($post->json('data.status'))->toBe('scheduled');
});

it('publishes a scheduled post via cms:publish-scheduled-posts once scheduled_at has arrived', function () {
    $blog = app(TenantContext::class)->scope($this->storeA, fn () => Blog::query()->create(['title' => 'News', 'slug' => 'news']));
    $post = app(TenantContext::class)->scope($this->storeA, fn () => $blog->posts()->create([
        'title' => 'Due Now', 'slug' => 'due-now', 'body' => 'x', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute(),
    ]));

    Artisan::call('cms:publish-scheduled-posts');

    app(TenantContext::class)->scope($this->storeA, function () use ($post) {
        expect($post->fresh()->status->value)->toBe('published');
    });
});

it('only shows published posts on the storefront, never draft or scheduled', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $blog = Blog::query()->create(['title' => 'News', 'slug' => 'news']);
        $blog->posts()->create(['title' => 'Draft Post', 'slug' => 'draft-post', 'body' => 'x', 'status' => 'draft']);
        $blog->posts()->create(['title' => 'Scheduled Post', 'slug' => 'scheduled-post', 'body' => 'x', 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
        $blog->posts()->create(['title' => 'Live Post', 'slug' => 'live-post', 'body' => 'x', 'status' => 'published', 'published_at' => now()]);
    });

    domainForStore($this->storeA, 'cms-blog-test.localhost');

    $index = $this->getJson(storefrontUrl('cms-blog-test.localhost', '/api/v1/storefront/blogs/news/posts'))->assertOk();
    expect(collect($index->json('data'))->pluck('slug')->all())->toBe(['live-post']);

    $this->getJson(storefrontUrl('cms-blog-test.localhost', '/api/v1/storefront/blog/posts/draft-post'))->assertStatus(404);
    $this->getJson(storefrontUrl('cms-blog-test.localhost', '/api/v1/storefront/blog/posts/live-post'))->assertOk();
});

it('never lets Store A read, edit, publish, or delete a Store B blog or post', function () {
    $postB = app(TenantContext::class)->scope($this->storeB, fn () => $this->blogB->posts()->create(['title' => 'x', 'slug' => 'x', 'body' => 'x']));

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/blogs/{$this->blogB->id}", tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/blogs/{$this->blogB->id}", ['title' => 'Hijacked'], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/blogs/{$this->blogB->id}/posts", tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/blog-posts/{$postB->id}", tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/blog-posts/{$postB->id}", ['title' => 'Hijacked'], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/blog-posts/{$postB->id}/publish", [], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->deleteJson("/api/v1/blog-posts/{$postB->id}", [], tenantHeader($this->storeA))->assertNotFound();
});
