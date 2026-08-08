<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;
use Illuminate\Validation\ValidationException;

final class UpdateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Category $category, array $data): Category
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->assertNotCyclic($category, $data['parent_id']);
        }

        $category->update($data);

        return $category;
    }

    /**
     * Walks the proposed parent's ancestor chain to make sure $category
     * isn't among them, which would turn the tree into a cycle.
     */
    private function assertNotCyclic(Category $category, string $newParentId): void
    {
        if ($newParentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $ancestor = Category::query()->find($newParentId);

        while ($ancestor !== null) {
            if ($ancestor->id === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'This would create a category cycle.',
                ]);
            }

            $ancestor = $ancestor->parent_id !== null ? Category::query()->find($ancestor->parent_id) : null;
        }
    }
}
