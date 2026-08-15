<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Models\CustomerTag;
use Illuminate\Support\Str;

final class CreateCustomerTag
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(array $data): CustomerTag
    {
        return CustomerTag::query()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'is_system' => false,
        ]);
    }
}
