<?php

namespace App\Domain\Search\Support;

/**
 * Every facet dimension a search can be filtered by (spec section 6).
 * `variantOptions` is keyed by option name (e.g. "Color") to a list of
 * accepted values (e.g. ["Red", "Blue"]) — an AND across option names,
 * an OR within one option's values, the standard faceted-nav shape.
 */
final readonly class SearchFilters
{
    /**
     * @param  list<string>  $categoryIds
     * @param  list<string>  $collectionIds
     * @param  list<string>  $vendors
     * @param  list<string>  $productTypes
     * @param  list<string>  $tags
     * @param  array<string, list<string>>  $variantOptions
     */
    public function __construct(
        public array $categoryIds = [],
        public array $collectionIds = [],
        public array $vendors = [],
        public array $productTypes = [],
        public array $tags = [],
        public array $variantOptions = [],
        public ?int $priceMin = null,
        public ?int $priceMax = null,
        public ?bool $availability = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            categoryIds: array_values(array_filter((array) ($data['category_ids'] ?? []))),
            collectionIds: array_values(array_filter((array) ($data['collection_ids'] ?? []))),
            vendors: array_values(array_filter((array) ($data['vendors'] ?? []))),
            productTypes: array_values(array_filter((array) ($data['product_types'] ?? []))),
            tags: array_values(array_filter((array) ($data['tags'] ?? []))),
            variantOptions: array_map(fn ($values) => array_values(array_filter((array) $values)), (array) ($data['variant_options'] ?? [])),
            priceMin: isset($data['price_min']) ? (int) $data['price_min'] : null,
            priceMax: isset($data['price_max']) ? (int) $data['price_max'] : null,
            availability: isset($data['availability']) ? (bool) $data['availability'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'category_ids' => $this->categoryIds,
            'collection_ids' => $this->collectionIds,
            'vendors' => $this->vendors,
            'product_types' => $this->productTypes,
            'tags' => $this->tags,
            'variant_options' => $this->variantOptions,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'availability' => $this->availability,
        ];
    }
}
