<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class DeleteCategory
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Category $category): void
    {
        $categoryId = $category->id;

        $category->delete();

        $this->recordOutboxEvent->handle('CategoryUpdated', 'Category', $categoryId, ['category_id' => $categoryId]);
    }
}
