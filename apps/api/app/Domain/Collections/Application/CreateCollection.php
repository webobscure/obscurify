<?php

namespace App\Domain\Collections\Application;

use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Collections\Models\Collection;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Str;

final class CreateCollection
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array{title: string, slug?: string, description?: string|null, status?: string}  $data
     */
    public function handle(array $data): Collection
    {
        $data['slug'] ??= Str::slug($data['title']);
        $data['status'] ??= CollectionStatus::Draft->value;

        $collection = Collection::query()->create($data);

        $this->recordOutboxEvent->handle('CollectionUpdated', 'Collection', $collection->id, ['collection_id' => $collection->id]);

        return $collection;
    }
}
