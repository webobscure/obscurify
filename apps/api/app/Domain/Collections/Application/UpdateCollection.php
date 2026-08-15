<?php

namespace App\Domain\Collections\Application;

use App\Domain\Collections\Models\Collection;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class UpdateCollection
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Collection $collection, array $data): Collection
    {
        $collection->update($data);

        $this->recordOutboxEvent->handle('CollectionUpdated', 'Collection', $collection->id, ['collection_id' => $collection->id]);

        return $collection;
    }
}
