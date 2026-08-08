<?php

namespace App\Domain\Media\Application;

use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

final class DeleteMedia
{
    public function handle(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);

        $media->delete();
    }
}
