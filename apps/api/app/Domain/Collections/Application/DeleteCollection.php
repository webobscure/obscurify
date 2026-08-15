<?php

namespace App\Domain\Collections\Application;

use App\Domain\Collections\Models\Collection;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class DeleteCollection
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Collection $collection): void
    {
        $collectionId = $collection->id;

        $collection->delete();

        $this->recordOutboxEvent->handle('CollectionUpdated', 'Collection', $collectionId, ['collection_id' => $collectionId]);
    }
}
