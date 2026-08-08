<?php

namespace App\Domain\Media\Application;

use App\Domain\Media\Models\Media;

final class UpdateMedia
{
    /**
     * @param  array{alt?: string|null, position?: int}  $data
     */
    public function handle(Media $media, array $data): Media
    {
        $media->update($data);

        return $media;
    }
}
