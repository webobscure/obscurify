<?php

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Models\WorkflowCondition;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eager-loads a condition tree's `children` relation to whatever depth
 * it actually goes — identical rationale and implementation to
 * SegmentRuleTreeLoader (M18); see that class's docblock for why
 * `flatMap()` needs explicit rewrapping.
 */
final class WorkflowConditionTreeLoader
{
    private const MAX_DEPTH = 10;

    /**
     * @param  Collection<int, WorkflowCondition>  $rootConditions
     */
    public static function load(Collection $rootConditions): void
    {
        $level = $rootConditions;

        for ($depth = 0; $depth < self::MAX_DEPTH && $level->isNotEmpty(); $depth++) {
            $level->loadMissing('children');
            $level = self::nextLevel($level);
        }
    }

    /**
     * @param  Collection<int, WorkflowCondition>  $level
     * @return Collection<int, WorkflowCondition>
     */
    private static function nextLevel(Collection $level): Collection
    {
        return Collection::make($level->flatMap(fn (WorkflowCondition $condition) => $condition->children)->all());
    }
}
