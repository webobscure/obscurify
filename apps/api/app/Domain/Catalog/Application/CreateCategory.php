<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Str;

final class CreateCategory
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * @param  array{title: string, slug?: string, parent_id?: string|null, position?: int}  $data
     */
    public function handle(array $data): Category
    {
        $data['slug'] ??= Str::slug($data['title']);
        $data['position'] ??= 0;

        $category = Category::query()->create($data);

        $this->recordOutboxEvent->handle('CategoryUpdated', 'Category', $category->id, ['category_id' => $category->id]);

        return $category;
    }
}
