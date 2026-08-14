<?php

use App\Domain\Builder\Application\SeedBuilderLibrary;
use App\Domain\Builder\Models\BlockInstance;
use App\Domain\Builder\Models\BuilderRevision;
use App\Domain\Builder\Models\PageLayout;
use App\Domain\Builder\Models\SectionInstance;
use App\Domain\Cms\Models\ActivePageVersion;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    // A theme with the full built-in library seeded, active for the
    // store, so section/block handles actually resolve — mirrors the
    // real flow: a theme always exists before a merchant opens the
    // Builder.
    app(TenantContext::class)->scope($this->storeA, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(SeedBuilderLibrary::class)->handle($theme->versions()->firstOrFail());
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });

    $this->pageA = app(TenantContext::class)->scope($this->storeA, function () {
        $page = Page::query()->create(['title' => 'Home Builder Page', 'slug' => 'home-builder', 'status' => 'draft']);
        PageVersion::query()->create(['page_id' => $page->id, 'version_number' => 1, 'status' => 'draft', 'sections' => []]);

        return $page;
    });

    $this->pageB = app(TenantContext::class)->scope($this->storeB, function () {
        $page = Page::query()->create(['title' => 'Store B Page', 'slug' => 'store-b-page', 'status' => 'draft']);
        PageVersion::query()->create(['page_id' => $page->id, 'version_number' => 1, 'status' => 'draft', 'sections' => []]);

        return $page;
    });
});

it('lazily bootstraps an empty PageLayout the first time the builder opens a page', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/builder/pages/{$this->pageA->id}", tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data.sections'))->toBe([])
        ->and($response->json('data.can_undo'))->toBeFalse()
        ->and($response->json('data.can_redo'))->toBeFalse();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(PageLayout::query()->where('page_version_id', $this->pageA->versions()->first()->id)->exists())->toBeTrue();
    });
});

it('persists a drag-and-drop save into relational rows, PageVersion.sections, and a new revision', function () {
    $sections = [[
        'id' => 'sec-1',
        'section_handle' => 'hero',
        'settings' => ['heading' => 'Builder Hero'],
        'blocks' => [
            ['id' => 'blk-1', 'block_handle' => 'button', 'settings' => ['label' => 'Shop now']],
        ],
    ]];

    $response = $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/builder/pages/{$this->pageA->id}",
        ['sections' => $sections],
        tenantHeader($this->storeA),
    )->assertOk();

    expect($response->json('data.sections.0.settings.heading'))->toBe('Builder Hero')
        ->and($response->json('data.sections.0.blocks.0.settings.label'))->toBe('Shop now')
        ->and($response->json('data.can_undo'))->toBeTrue();

    app(TenantContext::class)->scope($this->storeA, function () {
        $version = $this->pageA->versions()->firstOrFail();
        expect($version->sections)->toHaveCount(1)
            ->and($version->sections[0]['settings']['heading'])->toBe('Builder Hero');

        $layout = PageLayout::query()->where('page_version_id', $version->id)->firstOrFail();
        expect(SectionInstance::query()->where('page_layout_id', $layout->id)->count())->toBe(1)
            ->and(BlockInstance::query()->whereHas('sectionInstance', fn ($q) => $q->where('page_layout_id', $layout->id))->count())->toBe(1)
            ->and(BuilderRevision::query()->where('page_layout_id', $layout->id)->count())->toBe(2); // baseline + this save
    });
});

it('persists nested blocks and resolves them through ThemeRenderer without a rendering-engine rewrite', function () {
    $sections = [[
        'id' => 'sec-1',
        'section_handle' => 'hero',
        'settings' => [],
        'blocks' => [
            ['id' => 'parent', 'block_handle' => 'accordion', 'settings' => ['title' => 'FAQ'], 'blocks' => [
                ['id' => 'child', 'block_handle' => 'paragraph', 'settings' => ['text' => 'Nested answer']],
            ]],
        ],
    ]];

    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/builder/pages/{$this->pageA->id}",
        ['sections' => $sections],
        tenantHeader($this->storeA),
    )->assertOk();

    domainForStore($this->storeA, 'builder-nested-test.localhost');
    $slug = $this->pageA->slug;

    // Publish so the page is live, then render it through the exact
    // same storefront path — the nested block must survive the full
    // relational round trip (save -> serialize -> PageVersion.sections
    // -> publish -> ThemeRenderer) and actually appear, not just be
    // editable.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/publish", [], tenantHeader($this->storeA))->assertOk();

    $response = $this->getJson(storefrontUrl('builder-nested-test.localhost', "/api/v1/storefront/pages/{$slug}"))->assertOk();

    expect($response->json('data.rendered.sections.0.blocks.0.handle'))->toBe('accordion')
        ->and($response->json('data.rendered.sections.0.blocks.0.blocks.0.handle'))->toBe('paragraph')
        ->and($response->json('data.rendered.sections.0.blocks.0.blocks.0.settings.text'))->toBe('Nested answer');
});

it('drops an unknown section handle the same way ThemeRenderer already tolerates, in relational rows too', function () {
    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/builder/pages/{$this->pageA->id}",
        ['sections' => [['id' => 'x', 'section_handle' => 'does-not-exist', 'settings' => [], 'blocks' => []]]],
        tenantHeader($this->storeA),
    )->assertOk();

    domainForStore($this->storeA, 'builder-unknown-handle-test.localhost');
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/publish", [], tenantHeader($this->storeA))->assertOk();

    $response = $this->getJson(storefrontUrl('builder-unknown-handle-test.localhost', "/api/v1/storefront/pages/{$this->pageA->slug}"))->assertOk();

    expect($response->json('data.rendered.sections'))->toBe([]);
});

it('undoes and redoes across saves, and lists a restorable revision timeline', function () {
    $save = fn (string $heading) => $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/builder/pages/{$this->pageA->id}",
        ['sections' => [['id' => 'x', 'section_handle' => 'hero', 'settings' => ['heading' => $heading], 'blocks' => []]]],
        tenantHeader($this->storeA),
    )->assertOk();

    $save('First');
    $save('Second');

    $undo = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/undo", [], tenantHeader($this->storeA))->assertOk();
    expect($undo->json('data.sections.0.settings.heading'))->toBe('First')
        ->and($undo->json('data.can_redo'))->toBeTrue();

    $redo = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/redo", [], tenantHeader($this->storeA))->assertOk();
    expect($redo->json('data.sections.0.settings.heading'))->toBe('Second');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/redo", [], tenantHeader($this->storeA))->assertStatus(422);

    $revisions = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/builder/pages/{$this->pageA->id}/revisions", tenantHeader($this->storeA))->assertOk();
    expect($revisions->json('data'))->toHaveCount(3); // baseline + 2 saves

    $firstRevisionId = collect($revisions->json('data'))->sortBy('sequence')->values()[0]['id'];
    $restored = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/builder/pages/{$this->pageA->id}/revisions/{$firstRevisionId}/restore",
        [],
        tenantHeader($this->storeA),
    )->assertOk();
    expect($restored->json('data.sections'))->toBe([]); // the baseline (empty) revision
});

it('publish/duplicate/rollback reuse the exact same Cms actions, not a parallel implementation', function () {
    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/builder/pages/{$this->pageA->id}",
        ['sections' => [['id' => 'x', 'section_handle' => 'hero', 'settings' => ['heading' => 'V1'], 'blocks' => []]]],
        tenantHeader($this->storeA),
    )->assertOk();

    $published = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/publish", [], tenantHeader($this->storeA))->assertOk();
    expect($published->json('data.is_active'))->toBeTrue()
        ->and($published->json('data.versions'))->toHaveCount(2);

    $v1Id = collect($published->json('data.versions'))->firstWhere('version_number', 1)['id'];

    $duplicated = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/duplicate", [], tenantHeader($this->storeA))->assertCreated();
    expect($duplicated->json('data.title'))->toBe('Home Builder Page (copy)');

    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/builder/pages/{$this->pageA->id}",
        ['sections' => [['id' => 'x', 'section_handle' => 'hero', 'settings' => ['heading' => 'V2'], 'blocks' => []]]],
        tenantHeader($this->storeA),
    )->assertOk();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageA->id}/publish", [], tenantHeader($this->storeA))->assertOk();

    $rolledBack = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/builder/pages/{$this->pageA->id}/rollback",
        ['page_version_id' => $v1Id],
        tenantHeader($this->storeA),
    )->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () use ($v1Id) {
        $active = ActivePageVersion::query()->firstOrFail();
        expect($active->page_version_id)->toBe($v1Id);
    });
});

it('never lets Store A read, edit, publish, undo, or restore a Store B builder page', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/builder/pages/{$this->pageB->id}", tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/builder/pages/{$this->pageB->id}", ['sections' => []], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageB->id}/publish", [], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/builder/pages/{$this->pageB->id}/undo", [], tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/builder/pages/{$this->pageB->id}/revisions", tenantHeader($this->storeA))->assertNotFound();
});
