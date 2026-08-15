<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\CustomerSegmentStatus;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use Illuminate\Support\Facades\DB;

final class CreateCustomerSegment
{
    public function __construct(private readonly ReplaceSegmentRules $replaceSegmentRules) {}

    /**
     * @param  array{name: string, description?: string|null, rules?: list<array<string, mixed>>}  $data
     */
    public function handle(array $data): CustomerSegment
    {
        return DB::transaction(function () use ($data) {
            $segment = CustomerSegment::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => CustomerSegmentStatus::Active->value,
            ]);

            if (! empty($data['rules'])) {
                $this->replaceSegmentRules->handle(SegmentableType::CustomerSegment, $segment->id, $data['rules']);
            }

            return $segment;
        });
    }
}
