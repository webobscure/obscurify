<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryItem;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->product = app(TenantContext::class)->scope($this->store, fn () => Product::factory()->create());

    [$this->color, $this->black, $this->white] = app(TenantContext::class)->scope($this->store, function () {
        $color = ProductOption::factory()->create(['product_id' => $this->product->id, 'name' => 'Color']);
        $black = ProductOptionValue::factory()->create(['product_option_id' => $color->id, 'value' => 'Black']);
        $white = ProductOptionValue::factory()->create(['product_option_id' => $color->id, 'value' => 'White']);

        return [$color, $black, $white];
    });
});

it('creates a variant with an option combination and auto-provisions an inventory item', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1500,
            'sku' => 'TSHIRT-BLACK',
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertCreated()
        ->assertJsonPath('data.title', 'Black')
        ->assertJsonPath('data.sku', 'TSHIRT-BLACK');

    app(TenantContext::class)->scope($this->store, function () use ($response) {
        $variant = ProductVariant::query()->findOrFail($response->json('data.id'));

        expect(InventoryItem::query()->where('product_variant_id', $variant->id)->exists())->toBeTrue();
    });
});

it('rejects two variants with the same option combination', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1200,
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertStatus(422);

    app(TenantContext::class)->scope($this->store, function () {
        expect(ProductVariant::query()->count())->toBe(1);
    });
});

it('allows a different combination on the same product', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$this->white->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    app(TenantContext::class)->scope($this->store, function () {
        expect(ProductVariant::query()->count())->toBe(2);
    });
});

it('rejects an option value that does not belong to the product', function () {
    $otherProduct = app(TenantContext::class)->scope($this->store, fn () => Product::factory()->create());
    $otherOption = app(TenantContext::class)->scope($this->store, fn () => ProductOption::factory()->create(['product_id' => $otherProduct->id]));
    $otherValue = app(TenantContext::class)->scope($this->store, fn () => ProductOptionValue::factory()->create(['product_option_id' => $otherOption->id]));

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$otherValue->id],
        ], tenantHeader($this->store))
        ->assertStatus(422);
});

it('rejects two values from the same option on one variant', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$this->black->id, $this->white->id],
        ], tenantHeader($this->store))
        ->assertStatus(422);
});

it('rejects a duplicate SKU within the same store', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'sku' => 'DUP-SKU',
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'sku' => 'DUP-SKU',
            'option_value_ids' => [$this->white->id],
        ], tenantHeader($this->store))
        ->assertStatus(422);
});

it('allows the same SKU in a different store', function () {
    $otherUser = User::factory()->create();
    $otherStore = createStoreForUser($otherUser);
    $otherProduct = app(TenantContext::class)->scope($otherStore, fn () => Product::factory()->create());

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'sku' => 'SHARED-SKU',
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($otherUser, 'sanctum')
        ->postJson("/api/v1/products/{$otherProduct->id}/variants", [
            'price_amount' => 1000,
            'sku' => 'SHARED-SKU',
        ], tenantHeader($otherStore))
        ->assertCreated();
});

it('allows multiple variants with no SKU on the same product', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$this->white->id],
        ], tenantHeader($this->store))
        ->assertCreated();

    app(TenantContext::class)->scope($this->store, function () {
        expect(ProductVariant::query()->whereNull('sku')->count())->toBe(2);
    });
});

it('attaches an image to a variant and surfaces it on the product show endpoint', function () {
    Storage::fake(config('filesystems.default'));

    $variant = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants", [
            'price_amount' => 1000,
            'option_value_ids' => [$this->black->id],
        ], tenantHeader($this->store))
        ->assertCreated()
        ->json('data');

    $file = UploadedFile::fake()->image('variant.jpg');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/products/{$this->product->id}/variants/{$variant['id']}/media", ['file' => $file], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/products/{$this->product->id}", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonCount(1, 'data.variants.0.media')
        ->assertJsonPath('data.variants.0.media.0.entity_type', 'product_variant');
});
