<?php

namespace App\Domain\Media\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Media\Application\AttachMedia;
use App\Domain\Media\Enums\MediaEntityType;
use App\Domain\Media\Http\Requests\StoreMediaRequest;
use App\Domain\Media\Http\Resources\MediaResource;
use App\Http\Controllers\Controller;

final class ProductMediaController extends Controller
{
    public function store(StoreMediaRequest $request, Product $product, AttachMedia $action): MediaResource
    {
        $media = $action->handle(
            MediaEntityType::Product,
            $product->id,
            $request->file('file'),
            $request->safe()->except('file'),
        );

        return new MediaResource($media);
    }
}
