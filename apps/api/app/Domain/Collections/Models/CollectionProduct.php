<?php

namespace App\Domain\Collections\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CollectionProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $store_id
 * @property string $collection_id
 * @property string $product_id
 */
class CollectionProduct extends Model
{
    /** @use HasFactory<CollectionProductFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): CollectionProductFactory
    {
        return CollectionProductFactory::new();
    }

    protected $fillable = [
        'collection_id',
        'product_id',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
