<?php

namespace App\Domain\CustomerIntelligence\Support;

use App\Domain\CustomerIntelligence\Models\SegmentRule;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eager-loads a rule tree's `children` relation to whatever depth it
 * actually goes, level by level, rather than a fixed dot-notation path
 * (`children.children.children`) that would silently under-load a
 * deeper-nested tree — see SegmentRuleResource's docblock on why an
 * under-loaded group node is a bug, not a valid empty state.
 *
 * Typed against the Eloquent Collection specifically (not the base
 * Support\Collection every other class in this domain uses) because
 * `loadMissing()` only exists on it, and `flatMap()` — used to move to
 * the next tree level — degrades to the base Collection unless
 * explicitly rewrapped, which `nextLevel()` below does.
 */
final class SegmentRuleTreeLoader
{
    private const MAX_DEPTH = 10;

    /**
     * @param  Collection<int, SegmentRule>  $rootRules
     */
    public static function load(Collection $rootRules): void
    {
        $level = $rootRules;

        for ($depth = 0; $depth < self::MAX_DEPTH && $level->isNotEmpty(); $depth++) {
            $level->loadMissing('children');
            $level = self::nextLevel($level);
        }
    }

    /**
     * @param  Collection<int, SegmentRule>  $level
     * @return Collection<int, SegmentRule>
     */
    private static function nextLevel(Collection $level): Collection
    {
        return Collection::make($level->flatMap(fn (SegmentRule $rule) => $rule->children)->all());
    }
}
