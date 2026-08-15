<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupStatus;
use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use Illuminate\Support\Facades\DB;

final class CreateCustomerGroup
{
    public function __construct(private readonly ReplaceSegmentRules $replaceSegmentRules) {}

    /**
     * @param  array{name: string, description?: string|null, type: string, rules?: list<array<string, mixed>>}  $data
     */
    public function handle(array $data): CustomerGroup
    {
        return DB::transaction(function () use ($data) {
            $group = CustomerGroup::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'status' => CustomerGroupStatus::Active->value,
            ]);

            if ($group->type !== CustomerGroupType::Manual && ! empty($data['rules'])) {
                $this->replaceSegmentRules->handle(SegmentableType::CustomerGroup, $group->id, $data['rules']);
            }

            return $group;
        });
    }
}
