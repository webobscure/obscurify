<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;

final class DeleteCategory
{
    public function handle(Category $category): void
    {
        $category->delete();
    }
}
