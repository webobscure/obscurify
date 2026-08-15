<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Models\SegmentRule;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a CustomerGroup's or CustomerSegment's entire rule tree
 * atomically — the segment builder always sends the complete tree, never
 * a partial patch, so "replace" (delete then recreate) is simpler and
 * safer than diffing against whatever was there before.
 *
 * Input shape: a list of top-level nodes (implicitly ANDed — see
 * SegmentRuleEngine), each either:
 *   - a *condition* node: `{field, operator, value}`
 *   - a *group* node: `{boolean_operator: 'and'|'or', children: [...]}`
 * (recursively, for nested groups).
 */
final class ReplaceSegmentRules
{
    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    public function handle(SegmentableType $segmentableType, string $segmentableId, array $nodes): void
    {
        DB::transaction(function () use ($segmentableType, $segmentableId, $nodes) {
            SegmentRule::query()
                ->where('segmentable_type', $segmentableType->value)
                ->where('segmentable_id', $segmentableId)
                ->delete();

            $this->createNodes($segmentableType, $segmentableId, null, $nodes);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function createNodes(SegmentableType $segmentableType, string $segmentableId, ?string $parentId, array $nodes): void
    {
        foreach ($nodes as $position => $node) {
            $isGroup = array_key_exists('boolean_operator', $node) && $node['boolean_operator'] !== null;

            $rule = SegmentRule::query()->create([
                'segmentable_type' => $segmentableType->value,
                'segmentable_id' => $segmentableId,
                'parent_id' => $parentId,
                'boolean_operator' => $isGroup ? $node['boolean_operator'] : null,
                'field' => $isGroup ? null : $node['field'],
                'operator' => $isGroup ? null : $node['operator'],
                'value' => $isGroup ? null : ($node['value'] ?? null),
                'position' => $position,
            ]);

            if ($isGroup) {
                $this->createNodes($segmentableType, $segmentableId, $rule->id, $node['children'] ?? []);
            }
        }
    }
}
