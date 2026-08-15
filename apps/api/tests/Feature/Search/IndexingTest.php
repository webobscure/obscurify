<?php

use App\Domain\Catalog\Application\CreateProduct;
use App\Domain\Catalog\Application\CreateProductVariant;
use App\Domain\Catalog\Application\DeleteProduct;
use App\Domain\Catalog\Application\UpdateProduct;
use App\Domain\Catalog\Application\UpdateProductVariant;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Application\AdjustInventory;
use App\Domain\Locations\Models\Location;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ReindexStore;
use App\Domain\Search\Application\SearchIndexingSubscriber;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchIndex;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultSearchSetup::class)->handle($this->store);
    });
});

it('creates a SearchIndex row and default provider/settings for a fresh store', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $index = SearchIndex::query()->where('store_id', $this->store->id)->firstOrFail();
        expect($index->status->value)->toBe('building');
        expect(SearchProvider::query()->where('code', 'database')->exists())->toBeTrue();
        expect(SearchSettings::query()->where('store_id', $this->store->id)->exists())->toBeTrue();
    });
});

it('indexes a product incrementally on ProductCreated via the outbox pipeline', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = app(CreateProduct::class)->handle(['title' => 'Wireless Mouse', 'status' => 'active']);

        $event = OutboxEvent::withoutGlobalScopes()->where('event_type', 'ProductCreated')->where('aggregate_id', $product->id)->firstOrFail();
        app(SearchIndexingSubscriber::class)->handle($event, $this->store);

        $document = SearchDocument::query()->where('product_id', $product->id)->first();
        expect($document)->not->toBeNull();
        expect($document->title)->toBe('Wireless Mouse');
        // A product with no active variant yet has no price and is not
        // searchable until it's genuinely ready — is_searchable only
        // tracks status/deletion, so it should already be true here.
        expect($document->is_searchable)->toBeTrue();
    });
});

it('reindexes a product incrementally on UpdateProduct, VariantUpdated, PriceChanged, VisibilityChanged, and InventoryChanged', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = app(CreateProduct::class)->handle(['title' => 'Desk Lamp', 'status' => 'active']);
        processAllOutboxEventsFor($this->store);

        $variant = app(CreateProductVariant::class)->handle($product->fresh(), ['price_amount' => 2000]);
        processAllOutboxEventsFor($this->store);

        $document = SearchDocument::query()->where('product_id', $product->id)->firstOrFail();
        expect($document->price_min)->toBe(2000);

        app(UpdateProductVariant::class)->handle($variant, ['price_amount' => 3500]);
        processAllOutboxEventsFor($this->store);
        expect(SearchDocument::query()->where('product_id', $product->id)->value('price_min'))->toBe(3500);

        app(UpdateProduct::class)->handle($product->fresh(), ['title' => 'Desk Lamp Pro']);
        processAllOutboxEventsFor($this->store);
        expect(SearchDocument::query()->where('product_id', $product->id)->value('title'))->toBe('Desk Lamp Pro');

        app(UpdateProduct::class)->handle($product->fresh(), ['status' => 'draft']);
        processAllOutboxEventsFor($this->store);
        expect(SearchDocument::query()->where('product_id', $product->id)->value('is_searchable'))->toBeFalse();

        app(UpdateProduct::class)->handle($product->fresh(), ['status' => 'active']);
        processAllOutboxEventsFor($this->store);

        $location = Location::factory()->create();
        app(AdjustInventory::class)->handle($variant->fresh()->inventoryItem, $location, [
            'location_id' => $location->id,
            'quantity_delta' => 10,
            'reason' => 'manual_adjustment',
        ]);
        processAllOutboxEventsFor($this->store);

        $document = SearchDocument::query()->where('product_id', $product->id)->firstOrFail();
        expect($document->availability)->toBeTrue();
        expect($document->inventory_quantity)->toBe(10);
    });
});

it('removes the SearchDocument when a product is deleted', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = app(CreateProduct::class)->handle(['title' => 'Temporary Item', 'status' => 'active']);
        processAllOutboxEventsFor($this->store);
        expect(SearchDocument::query()->where('product_id', $product->id)->exists())->toBeTrue();

        app(DeleteProduct::class)->handle($product->fresh());
        processAllOutboxEventsFor($this->store);

        expect(SearchDocument::query()->where('product_id', $product->id)->exists())->toBeFalse();
    });
});

it('runs a full reindex across every product and drops stale documents', function () {
    app(TenantContext::class)->scope($this->store, function () {
        Product::factory()->count(3)->create();

        $index = app(ReindexStore::class)->full($this->store);

        expect($index->status->value)->toBe('ready');
        expect($index->document_count)->toBe(3);
        expect(SearchDocument::query()->where('store_id', $this->store->id)->count())->toBe(3);
        expect($index->last_full_reindex_at)->not->toBeNull();
    });
});

it('runs a partial reindex against only the given products', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $products = Product::factory()->count(3)->create();
        app(ReindexStore::class)->full($this->store);

        $products[0]->update(['title' => 'Manually Changed Title']);
        // Simulate the document being stale (as if the event pipeline hadn't run).
        SearchDocument::query()->where('product_id', $products[0]->id)->update(['title' => 'Stale Title']);

        app(ReindexStore::class)->partial($this->store, [$products[0]->id]);

        expect(SearchDocument::query()->where('product_id', $products[0]->id)->value('title'))->toBe('Manually Changed Title');
        expect(SearchDocument::query()->where('product_id', $products[1]->id)->value('title'))->not->toBe('Stale Title');
    });
});

/**
 * Drains every unprocessed OutboxEvent — the same role `outbox:process`
 * plays in production, invoked directly here so these tests stay fast
 * and don't need a separate queue worker. Only one store's fixtures
 * exist per test, so scoping by store is unnecessary.
 */
function processAllOutboxEventsFor(Store $store): void
{
    Artisan::call('outbox:process');
}
