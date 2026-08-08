<?php

namespace App\Domain\Collections\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $store_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property CollectionStatus $status
 */
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CollectionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<CollectionProduct, $this>
     */
    public function productLinks(): HasMany
    {
        return $this->hasMany(CollectionProduct::class);
    }

    /**
     * Read-only convenience relation for eager loading. Membership changes
     * always go through AttachProductToCollection/DetachProductFromCollection
     * — never call attach()/detach()/sync() on this relation, since that
     * would bypass CollectionProduct's tenant-scoping and cross-tenant
     * validation.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_products')->withTimestamps();
    }
}
