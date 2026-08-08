<?php

namespace App\Domain\Media\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Media\Application\AttachMedia;
use App\Domain\Media\Enums\MediaEntityType;
use App\Domain\Media\Http\Requests\StoreMediaRequest;
use App\Domain\Media\Http\Resources\MediaResource;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductVariantMediaController extends Controller
{
    public function store(StoreMediaRequest $request, Product $product, ProductVariant $variant, AttachMedia $action): MediaResource
    {
        if ($variant->product_id !== $product->id) {
            throw new NotFoundHttpException;
        }

        $media = $action->handle(
            MediaEntityType::ProductVariant,
            $variant->id,
            $request->file('file'),
            $request->safe()->except('file'),
        );

        return new MediaResource($media);
    }
}
