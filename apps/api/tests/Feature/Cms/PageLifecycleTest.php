<?php

use App\Domain\Cms\Application\PublishPageVersion;
use App\Domain\Cms\Models\ActivePageVersion;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->pageB = app(TenantContext::class)->scope($this->storeB, fn () => Page::query()->create(['title' => 'Store B Page', 'slug' => 'store-b-page', 'status' => 'draft']));
    app(TenantContext::class)->scope($this->storeB, fn () => PageVersion::query()->create(['page_id' => $this->pageB->id, 'version_number' => 1, 'status' => 'draft', 'sections' => []]));
});

it('creates a page seeded with a draft v1', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us',
        'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();

    expect($response->json('data.title'))->toBe('About Us')
        ->and($response->json('data.status'))->toBe('draft')
        ->and($response->json('data.is_active'))->toBeFalse()
        ->and($response->json('data.versions'))->toHaveCount(1)
        ->and($response->json('data.versions.0.version_number'))->toBe(1)
        ->and($response->json('data.versions.0.status'))->toBe('draft');
});

it('publishes a draft: freezes it, activates it, and opens a fresh draft', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us', 'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();

    $published = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/pages/{$created->json('data.id')}/publish",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    expect($published->json('data.is_active'))->toBeTrue()
        ->and($published->json('data.versions'))->toHaveCount(2);

    $versions = collect($published->json('data.versions'))->sortBy('version_number')->values();
    expect($versions[0]['status'])->toBe('published')
        ->and($versions[0]['published_at'])->not->toBeNull()
        ->and($versions[1]['status'])->toBe('draft')
        ->and($versions[1]['version_number'])->toBe(2);
});

it('rejects publishing a page version that is already published', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us', 'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/pages/{$created->json('data.id')}/publish", [], tenantHeader($this->storeA))->assertOk();

    $pageId = $created->json('data.id');
    $versions = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/pages/{$pageId}/versions", tenantHeader($this->storeA))->assertOk();
    $publishedVersion = collect($versions->json('data'))->firstWhere('status', 'published');

    expect(app(TenantContext::class)->scope($this->storeA, function () use ($publishedVersion) {
        try {
            app(PublishPageVersion::class)->handle(PageVersion::query()->findOrFail($publishedVersion['id']));

            return false;
        } catch (ValidationException) {
            return true;
        }
    }))->toBeTrue();
});

it('edits the current draft sections, then publish freezes that exact content', function () {
    // Preview renders through ThemeRenderer, which resolves the 'hero'
    // section handle against the store's active theme — needs one.
    app(TenantContext::class)->scope($this->storeA, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });

    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us', 'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();

    $draftVersionId = $created->json('data.versions.0.id');

    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/page-versions/{$draftVersionId}/sections",
        ['sections' => [['id' => 'hero-1', 'section_handle' => 'hero', 'settings' => ['heading' => 'Big Sale'], 'blocks' => []]]],
        tenantHeader($this->storeA),
    )->assertOk();

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/pages/{$created->json('data.id')}/publish",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    $preview = $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/pages/{$created->json('data.id')}/preview",
        tenantHeader($this->storeA),
    )->assertOk();

    // Preview now shows the NEW draft (cloned from what was just
    // published), which must carry the edited heading forward.
    expect($preview->json('data.sections.0.settings.heading'))->toBe('Big Sale');
});

it('rejects editing a published page version', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us', 'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();
    $draftVersionId = $created->json('data.versions.0.id');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/pages/{$created->json('data.id')}/publish", [], tenantHeader($this->storeA))->assertOk();

    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/page-versions/{$draftVersionId}/sections",
        ['sections' => []],
        tenantHeader($this->storeA),
    )->assertStatus(422);
});

it('rolls back to an older published version without touching the current draft', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us', 'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();
    $pageId = $created->json('data.id');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/pages/{$pageId}/publish", [], tenantHeader($this->storeA))->assertOk();
    $afterFirstPublish = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/pages/{$pageId}", tenantHeader($this->storeA))->assertOk();
    $v1Id = collect($afterFirstPublish->json('data.versions'))->firstWhere('version_number', 1)['id'];

    $afterSecondPublish = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/pages/{$pageId}/publish", [], tenantHeader($this->storeA))->assertOk();
    $v3DraftId = collect($afterSecondPublish->json('data.versions'))->firstWhere('version_number', 3)['id'];

    $rolledBack = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/page-versions/{$v1Id}/rollback",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    expect($rolledBack->json('data.versions'))->toHaveCount(3);

    app(TenantContext::class)->scope($this->storeA, function () use ($v1Id, $v3DraftId) {
        $active = ActivePageVersion::query()->firstOrFail();
        expect($active->page_version_id)->toBe($v1Id);

        $draft = PageVersion::query()->findOrFail($v3DraftId);
        expect($draft->status->value)->toBe('draft');
    });
});

it('rejects rolling back to a draft version', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us', 'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/page-versions/{$created->json('data.versions.0.id')}/rollback",
        [],
        tenantHeader($this->storeA),
    )->assertStatus(422);
});

it('duplicates a page into a fully independent copy', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/pages', [
        'title' => 'About Us', 'slug' => 'about-us',
    ], tenantHeader($this->storeA))->assertCreated();

    $duplicated = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/pages/{$created->json('data.id')}/duplicate",
        [],
        tenantHeader($this->storeA),
    )->assertCreated();

    expect($duplicated->json('data.id'))->not->toBe($created->json('data.id'))
        ->and($duplicated->json('data.title'))->toBe('About Us (copy)')
        ->and($duplicated->json('data.versions'))->toHaveCount(1);

    $copyVersionId = $duplicated->json('data.versions.0.id');
    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/page-versions/{$copyVersionId}/sections",
        ['sections' => [['id' => 'x', 'section_handle' => 'hero', 'settings' => [], 'blocks' => []]]],
        tenantHeader($this->storeA),
    )->assertOk();

    $original = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/pages/{$created->json('data.id')}", tenantHeader($this->storeA))->assertOk();
    expect($original->json('data.versions.0.sections'))->toBe([]);
});

it('never lets Store A read, edit, publish, duplicate, or roll back a Store B page', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/pages/{$this->pageB->id}", tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/pages/{$this->pageB->id}", ['title' => 'Hijacked'], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/pages/{$this->pageB->id}/publish", [], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/pages/{$this->pageB->id}/duplicate", [], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/pages/{$this->pageB->id}/preview", tenantHeader($this->storeA))->assertNotFound();

    $versionB = app(TenantContext::class)->scope($this->storeB, fn () => $this->pageB->versions()->first());
    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/page-versions/{$versionB->id}/sections", ['sections' => []], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/page-versions/{$versionB->id}/rollback", [], tenantHeader($this->storeA))->assertNotFound();
});
