<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Media\Models\Media;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->disk = config('filesystems.default');
    Storage::fake($this->disk);

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->productA = app(TenantContext::class)->scope($this->storeA, fn () => Product::factory()->create());

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->productB = app(TenantContext::class)->scope($this->storeB, fn () => Product::factory()->create());
});

it('attaches an image to a product and stores it on disk, not the database', function () {
    $file = UploadedFile::fake()->image('front.jpg');

    $response = $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/products/{$this->productA->id}/media", ['file' => $file, 'alt' => 'Front view'], tenantHeader($this->storeA))
        ->assertCreated()
        ->assertJsonPath('data.alt', 'Front view');

    $media = app(TenantContext::class)->scope($this->storeA, fn () => Media::query()->findOrFail($response->json('data.id')));

    expect($media->disk)->toBe($this->disk);
    Storage::disk($this->disk)->assertExists($media->path);
});

it('orders product media by position', function () {
    $first = UploadedFile::fake()->image('a.jpg');
    $second = UploadedFile::fake()->image('b.jpg');

    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/products/{$this->productA->id}/media", ['file' => $first], tenantHeader($this->storeA))
        ->assertCreated();

    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/products/{$this->productA->id}/media", ['file' => $second], tenantHeader($this->storeA))
        ->assertCreated();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect($this->productA->fresh()->media()->pluck('position')->all())->toBe([0, 1]);
    });
});

it('does not let Store A update or delete Store B media', function () {
    $file = UploadedFile::fake()->image('b.jpg');

    $response = $this->actingAs($this->userB, 'sanctum')
        ->postJson("/api/v1/products/{$this->productB->id}/media", ['file' => $file], tenantHeader($this->storeB))
        ->assertCreated();

    $mediaId = $response->json('data.id');

    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/media/{$mediaId}", ['alt' => 'Hacked'], tenantHeader($this->storeA))
        ->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/v1/media/{$mediaId}", [], tenantHeader($this->storeA))
        ->assertNotFound();

    expect(Media::withoutGlobalScopes()->find($mediaId))->not->toBeNull();
});

it('deletes the stored file when media is removed', function () {
    $file = UploadedFile::fake()->image('front.jpg');

    $response = $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/products/{$this->productA->id}/media", ['file' => $file], tenantHeader($this->storeA))
        ->assertCreated();

    $media = app(TenantContext::class)->scope($this->storeA, fn () => Media::query()->findOrFail($response->json('data.id')));
    $path = $media->path;

    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/v1/media/{$media->id}", [], tenantHeader($this->storeA))
        ->assertNoContent();

    Storage::disk($this->disk)->assertMissing($path);
});
