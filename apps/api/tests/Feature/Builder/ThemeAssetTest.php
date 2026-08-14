<?php

use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Models\ThemeAsset;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    // A relation accessed after scope() returns runs with no active
    // tenant — fetch everything the test body needs while still inside
    // the closure, not from the returned model afterward.
    [$this->themeA, $this->versionIdA] = app(TenantContext::class)->scope($this->storeA, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);

        return [$theme, $theme->versions()->firstOrFail()->id];
    });
    [$this->themeB, $this->versionIdB] = app(TenantContext::class)->scope($this->storeB, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'B Theme', 'slug' => 'b-theme']);

        return [$theme, $theme->versions()->firstOrFail()->id];
    });
});

it('uploads, lists, and deletes a theme asset for the builder picker', function () {
    $versionId = $this->versionIdA;

    $upload = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/theme-versions/{$versionId}/assets",
        ['type' => 'image', 'file' => UploadedFile::fake()->image('hero.jpg', 800, 600)],
        tenantHeader($this->storeA),
    )->assertCreated();

    // Storage::store() generates a random filename by design — assert
    // on the extension it preserves, not the original basename.
    expect($upload->json('data.type'))->toBe('image')
        ->and($upload->json('data.url'))->toContain('.jpg');

    $index = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/theme-versions/{$versionId}/assets", tenantHeader($this->storeA))->assertOk();
    expect($index->json('data'))->toHaveCount(1);

    $assetId = $upload->json('data.id');
    $path = app(TenantContext::class)->scope($this->storeA, fn () => ThemeAsset::query()->findOrFail($assetId)->path);
    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->userA, 'sanctum')->deleteJson("/api/v1/theme-assets/{$assetId}", [], tenantHeader($this->storeA))->assertNoContent();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a file over the size limit', function () {
    $versionId = $this->versionIdA;

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/theme-versions/{$versionId}/assets",
        ['type' => 'image', 'file' => UploadedFile::fake()->create('huge.jpg', 30000)],
        tenantHeader($this->storeA),
    )->assertStatus(422);
});

it('never lets Store A read, upload to, or delete a Store B theme version\'s assets', function () {
    $versionId = $this->versionIdB;

    $assetId = app(TenantContext::class)->scope($this->storeB, fn () => ThemeAsset::query()->create([
        'theme_version_id' => $versionId,
        'type' => 'image',
        'path' => 'theme-assets/fake.jpg',
        'disk' => 'public',
    ])->id);

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/theme-versions/{$versionId}/assets", tenantHeader($this->storeA))->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/theme-versions/{$versionId}/assets",
        ['type' => 'image', 'file' => UploadedFile::fake()->image('x.jpg')],
        tenantHeader($this->storeA),
    )->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->deleteJson("/api/v1/theme-assets/{$assetId}", [], tenantHeader($this->storeA))->assertNotFound();
});
