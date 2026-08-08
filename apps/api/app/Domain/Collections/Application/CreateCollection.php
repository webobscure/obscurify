<?php

namespace App\Domain\Collections\Application;

use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Collections\Models\Collection;
use Illuminate\Support\Str;

final class CreateCollection
{
    /**
     * @param  array{title: string, slug?: string, description?: string|null, status?: string}  $data
     */
    public function handle(array $data): Collection
    {
        $data['slug'] ??= Str::slug($data['title']);
        $data['status'] ??= CollectionStatus::Draft->value;

        return Collection::query()->create($data);
    }
}
