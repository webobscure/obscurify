<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerSegment
{
    public function __construct(private readonly ReplaceSegmentRules $replaceSegmentRules) {}

    /**
     * @param  array{name?: string, description?: string|null, status?: string, rules?: list<array<string, mixed>>}  $data
     */
    public function handle(CustomerSegment $segment, array $data): CustomerSegment
    {
        return DB::transaction(function () use ($segment, $data) {
            $segment->fill([
                'name' => $data['name'] ?? $segment->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $segment->description,
                'status' => $data['status'] ?? $segment->status,
            ])->save();

            if (array_key_exists('rules', $data)) {
                $this->replaceSegmentRules->handle(SegmentableType::CustomerSegment, $segment->id, $data['rules']);
            }

            return $segment;
        });
    }
}
