<?php

namespace App\Domain\Search\Application;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Search\Support\SearchProviderRegistry;
use App\Domain\Search\Support\SearchTextNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;

/**
 * Builds (or rebuilds) one product's SearchDocument row from its
 * current live state — the one place Product/ProductVariant/
 * InventoryLevel/Collection/Category are actually read for search
 * purposes (spec section 3). Every other read in this domain goes
 * through SearchDocument only.
 */
final class BuildSearchDocument
{
    public function __construct(
        private readonly SearchTextNormalizer $normalizer,
        private readonly SearchProviderRegistry $registry,
    ) {}

    public function handle(Product $product): SearchDocument
    {
        $product->loadMissing([
            'variants' => fn ($q) => $q->where('status', ProductStatus::Active),
            'variants.optionValues.option',
            'variants.inventoryItem.levels',
            'collections' => fn ($q) => $q->where('status', CollectionStatus::Active),
            'categories',
            'media',
        ]);

        $activeVariants = $product->variants;
        $prices = $activeVariants->pluck('price_amount')->filter(fn ($price) => $price !== null);

        $inventoryQuantity = 0;
        $availability = false;

        foreach ($activeVariants as $variant) {
            $item = $variant->inventoryItem;

            if ($item === null) {
                continue;
            }

            if (! $item->tracked) {
                $availability = true;

                continue;
            }

            $onHand = $item->levels->sum(fn ($level) => $level->on_hand - $level->reserved);
            $inventoryQuantity += max($onHand, 0);

            if ($onHand > 0) {
                $availability = true;
            }
        }

        $variantOptionValues = $activeVariants
            ->flatMap(fn ($variant) => $variant->optionValues->map(fn ($value) => [
                'option' => $value->option->name,
                'value' => $value->value,
            ])->all())
            ->unique(fn ($pair) => "{$pair['option']}:{$pair['value']}")
            ->values()
            ->all();

        $tags = $product->tags ?? [];
        $collectionTitles = $product->collections->pluck('title')->all();
        $categoryTitles = $product->categories->pluck('title')->all();
        $variantOptionText = collect($variantOptionValues)->pluck('value')->all();

        $searchText = $this->normalizer->normalize(implode(' ', array_filter([
            $product->title,
            $product->description,
            $product->vendor,
            $product->product_type,
            implode(' ', $tags),
            implode(' ', $collectionTitles),
            implode(' ', $categoryTitles),
            implode(' ', $variantOptionText),
        ])));

        $salesCount = (int) OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('financial_status', 'paid'))
            ->where('product_id', $product->id)
            ->sum('quantity');

        $thumbnail = $product->media->sortBy('position')->first();

        $isSearchable = $product->status === ProductStatus::Active && $product->deleted_at === null;

        $firstVariant = $activeVariants->first();
        $currency = $firstVariant !== null ? $firstVariant->currency : $product->store->default_currency;

        $attributes = ['store_id' => $product->store_id, 'product_id' => $product->id];
        $values = [
            'title' => $product->title,
            'slug' => $product->slug,
            'description' => $product->description,
            'vendor' => $product->vendor,
            'product_type' => $product->product_type,
            'tags' => $tags,
            'collection_ids' => $product->collections->pluck('id')->all(),
            'category_ids' => $product->categories->pluck('id')->all(),
            'variant_option_values' => $variantOptionValues,
            'price_min' => $prices->isEmpty() ? null : $prices->min(),
            'price_max' => $prices->isEmpty() ? null : $prices->max(),
            'currency' => $currency,
            'availability' => $availability,
            'inventory_quantity' => $inventoryQuantity,
            'status' => $product->status->value,
            'is_searchable' => $isSearchable,
            'thumbnail_url' => $thumbnail !== null ? Storage::disk($thumbnail->disk)->url($thumbnail->path) : null,
            'popularity' => $salesCount,
            'sales_count' => $salesCount,
            'search_score' => 0,
            'search_text' => $searchText,
            'product_created_at' => $product->created_at,
            'product_updated_at' => $product->updated_at,
        ];

        $document = $this->upsert($attributes, $values);

        $this->pushToActiveProvider($document);

        return $document;
    }

    /**
     * `updateOrCreate()` is SELECT-then-INSERT-or-UPDATE, not atomic —
     * two concurrent reindex passes for the same product (e.g. a
     * PriceChanged and a VariantUpdated event landing on the queue at
     * nearly the same time) could both attempt INSERT and the loser
     * would crash on `search_documents`' own (store_id, product_id)
     * unique constraint. Same fix AnalyticsAggregator::upsert() needed
     * for the identical race (see docs/adr/026-analytics-platform.md) —
     * catch the constraint violation and retry as a plain update.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    private function upsert(array $attributes, array $values): SearchDocument
    {
        try {
            return SearchDocument::query()->updateOrCreate($attributes, $values);
        } catch (UniqueConstraintViolationException) {
            SearchDocument::query()->where($attributes)->update($values);

            return SearchDocument::query()->where($attributes)->firstOrFail();
        }
    }

    /**
     * Writing the SearchDocument row (above) is the platform-owned
     * index and always happens; pushing to the store's currently
     * *active* provider is the extra step a real remote engine needs
     * (DatabaseSearchProvider's own index() is a no-op, since the row
     * just written already is its index — see that class's docblock).
     * Falls back to `database` when a store has no SearchSettings row
     * yet (a fresh store — see EnsureDefaultSearchSetup) or no active
     * provider assigned.
     */
    private function pushToActiveProvider(SearchDocument $document): void
    {
        $settings = SearchSettings::query()->where('store_id', $document->store_id)->first();
        $code = $settings?->activeProviderCode() ?? SearchProvider::DATABASE;

        if ($this->registry->has($code)) {
            $this->registry->resolve($code)->index($document);
        }
    }
}
