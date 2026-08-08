<?php

namespace App\Domain\Media\Http\Controllers;

use App\Domain\Media\Application\DeleteMedia;
use App\Domain\Media\Application\UpdateMedia;
use App\Domain\Media\Http\Requests\UpdateMediaRequest;
use App\Domain\Media\Http\Resources\MediaResource;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class MediaController extends Controller
{
    /**
     * $media is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's media.
     */
    public function update(UpdateMediaRequest $request, Media $media, UpdateMedia $action): MediaResource
    {
        $media = $action->handle($media, $request->validated());

        return new MediaResource($media);
    }

    public function destroy(Media $media, DeleteMedia $action): Response
    {
        $action->handle($media);

        return response()->noContent();
    }
}
