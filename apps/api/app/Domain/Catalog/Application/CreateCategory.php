<?php

namespace App\Domain\Catalog\Application;

use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Str;

final class CreateCategory
{
    /**
     * @param  array{title: string, slug?: string, parent_id?: string|null, position?: int}  $data
     */
    public function handle(array $data): Category
    {
        $data['slug'] ??= Str::slug($data['title']);
        $data['position'] ??= 0;

        return Category::query()->create($data);
    }
}
