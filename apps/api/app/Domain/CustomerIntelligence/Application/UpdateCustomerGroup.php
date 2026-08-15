<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Exceptions\ProtectedCustomerGroupException;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerGroup
{
    public function __construct(private readonly ReplaceSegmentRules $replaceSegmentRules) {}

    /**
     * @param  array{name?: string, description?: string|null, status?: string, rules?: list<array<string, mixed>>}  $data
     */
    public function handle(CustomerGroup $group, array $data): CustomerGroup
    {
        if ($group->type === CustomerGroupType::Protected && array_key_exists('name', $data) && $data['name'] !== $group->name) {
            throw ProtectedCustomerGroupException::cannotRename();
        }

        return DB::transaction(function () use ($group, $data) {
            $group->fill([
                'name' => $data['name'] ?? $group->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $group->description,
                'status' => $data['status'] ?? $group->status,
            ])->save();

            if ($group->type !== CustomerGroupType::Manual && array_key_exists('rules', $data)) {
                $this->replaceSegmentRules->handle(SegmentableType::CustomerGroup, $group->id, $data['rules']);
            }

            return $group;
        });
    }
}
