<?php

namespace App\Domain\CustomerIntelligence\Http\Requests\Concerns;

use App\Domain\CustomerIntelligence\Enums\SegmentRuleBoolean;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use Illuminate\Contracts\Validation\Validator;

/**
 * The `rules` array is an arbitrarily-nested tree (spec section 5), which
 * Laravel's declarative `rules()` array can't express — validated here
 * instead, via `withValidator()`, walking every node recursively. Each
 * node must be *either* a group node (`boolean_operator` + `children`)
 * *or* a condition node (`field` + `operator`, `value` optional) — never
 * both, never neither.
 */
trait ValidatesSegmentRuleTree
{
    protected function validateRuleTree(Validator $validator, string $inputKey = 'rules'): void
    {
        $nodes = $this->input($inputKey);

        if (! is_array($nodes)) {
            return;
        }

        $this->validateNodes($validator, $inputKey, $nodes);
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     */
    private function validateNodes(Validator $validator, string $path, array $nodes): void
    {
        foreach ($nodes as $index => $node) {
            $nodePath = "{$path}.{$index}";

            if (! is_array($node)) {
                $validator->errors()->add($nodePath, 'Each rule must be an object.');

                continue;
            }

            $isGroup = array_key_exists('boolean_operator', $node) && $node['boolean_operator'] !== null;
            $isCondition = array_key_exists('field', $node) && $node['field'] !== null;

            if ($isGroup === $isCondition) {
                $validator->errors()->add($nodePath, 'Each rule must be either a group (boolean_operator + children) or a condition (field + operator), not both or neither.');

                continue;
            }

            if ($isGroup) {
                if (! in_array($node['boolean_operator'], array_column(SegmentRuleBoolean::cases(), 'value'), true)) {
                    $validator->errors()->add("{$nodePath}.boolean_operator", 'Invalid boolean_operator.');
                }

                $children = $node['children'] ?? [];

                if (! is_array($children) || $children === []) {
                    $validator->errors()->add("{$nodePath}.children", 'A group rule must have at least one child.');

                    continue;
                }

                $this->validateNodes($validator, "{$nodePath}.children", $children);

                continue;
            }

            if (! in_array($node['field'], array_column(SegmentRuleField::cases(), 'value'), true)) {
                $validator->errors()->add("{$nodePath}.field", 'Invalid field.');
            }

            if (! isset($node['operator']) || ! in_array($node['operator'], array_column(SegmentRuleOperator::cases(), 'value'), true)) {
                $validator->errors()->add("{$nodePath}.operator", 'Invalid operator.');
            }
        }
    }
}
