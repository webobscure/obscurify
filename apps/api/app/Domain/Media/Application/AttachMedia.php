<?php

namespace App\Domain\Media\Application;

use App\Domain\Media\Enums\MediaEntityType;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\Media;
use Illuminate\Http\UploadedFile;

final class AttachMedia
{
    /**
     * @param  array{alt?: string|null, position?: int}  $data
     */
    public function handle(MediaEntityType $entityType, string $entityId, UploadedFile $file, array $data): Media
    {
        $disk = config('filesystems.default');

        $path = $file->store($entityType->value.'s', $disk);

        $data['position'] ??= Media::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->count();

        return Media::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'type' => MediaType::Image,
            'disk' => $disk,
            'path' => $path,
            ...$data,
        ]);
    }
}
